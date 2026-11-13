<?php

require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

/*
 * La operación solo puede ejecutarse mediante POST
 * y con un token CSRF válido.
 */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !validarTokenCSRF()
) {
    http_response_code(400);
    exit('Solicitud no válida.');
}


/*
 * Datos recibidos.
 */
$idSolicitud = filter_var(
    $_POST['id_solicitud'] ?? '',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

$condicionInicial = trim(
    $_POST['condicion_inicial'] ?? ''
);


/*
 * Usuario que está registrando físicamente
 * la entrega.
 *
 * RF-11:
 * entregado_por = usuario actual.
 */
$idUsuarioEntrega = (int)(
    $_SESSION['id_usuario'] ?? 0
);


/*
 * Validaciones básicas.
 */
if (
    !$idSolicitud ||
    $idUsuarioEntrega <= 0 ||
    $condicionInicial === ''
) {
    header(
        'Location: listar.php?estado=Aprobada&error=campos_entrega'
    );
    exit();
}


if (mb_strlen($condicionInicial) > 500) {
    header(
        'Location: listar.php?estado=Aprobada&error=condicion_entrega'
    );
    exit();
}


try {

    /*
     * Iniciar transacción.
     */
    $conexion->begin_transaction();


    /*
     * Obtener la solicitud aprobada.
     *
     * decidido_por = usuario que aprobó la solicitud.
     *
     * RF-11:
     * autorizado_por = decidido_por
     */
    $stmt = $conexion->prepare(
        "SELECT
            s.id_solicitud,
            s.id_usuario,
            s.id_equipo,
            s.estado_solicitud,
            s.decidido_por,

            e.id_estado,
            es.nombre_estado

         FROM solicitudes_prestamo s

         INNER JOIN equipos e
            ON e.id_equipo = s.id_equipo

         INNER JOIN estados_equipo es
            ON es.id_estado = e.id_estado

         WHERE s.id_solicitud = ?

         FOR UPDATE"
    );

    $stmt->bind_param(
        'i',
        $idSolicitud
    );

    $stmt->execute();

    $solicitud =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();


    /*
     * Verificar que exista.
     */
    if (!$solicitud) {
        throw new RuntimeException(
            'solicitud'
        );
    }


    /*
     * La solicitud debe estar aprobada.
     */
    if (
        $solicitud['estado_solicitud']
        !== 'Aprobada'
    ) {
        throw new RuntimeException(
            'estado'
        );
    }


    /*
     * Debe existir quién autorizó.
     */
    if (
        empty($solicitud['decidido_por'])
    ) {
        throw new RuntimeException(
            'autorizador'
        );
    }


    /*
     * El equipo debe estar reservado
     * antes de realizar la entrega.
     */
    if (
        $solicitud['nombre_estado']
        !== 'Reservado'
    ) {
        throw new RuntimeException(
            'equipo'
        );
    }


    /*
     * Verificar que todavía no exista
     * un préstamo para la solicitud.
     */
    $stmt = $conexion->prepare(
        "SELECT
            id_prestamo

         FROM prestamos

         WHERE id_solicitud = ?

         LIMIT 1

         FOR UPDATE"
    );

    $stmt->bind_param(
        'i',
        $idSolicitud
    );

    $stmt->execute();

    $prestamoExistente =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if ($prestamoExistente) {
        throw new RuntimeException(
            'ya_entregado'
        );
    }


    /*
     * Identificadores.
     */
    $idEquipo = (int)(
        $solicitud['id_equipo']
    );

    $idSolicitante = (int)(
        $solicitud['id_usuario']
    );


    /*
     * RF-11
     *
     * autorizado_por = quien aprobó
     * entregado_por  = quien registra la entrega
     */
    $idAutorizador = (int)(
        $solicitud['decidido_por']
    );


    /*
     * Obtener el estado "Prestado".
     */
    $stmt = $conexion->prepare(
        "SELECT
            id_estado

         FROM estados_equipo

         WHERE nombre_estado = 'Prestado'

         LIMIT 1"
    );

    $stmt->execute();

    $estadoPrestado =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if (!$estadoPrestado) {
        throw new RuntimeException(
            'estado_prestado'
        );
    }


    $idEstadoPrestado = (int)(
        $estadoPrestado['id_estado']
    );


    /*
     * Registrar el préstamo.
     *
     * entregado_por:
     * usuario que realiza la entrega.
     *
     * autorizado_por:
     * usuario que aprobó la solicitud.
     */
    $stmt = $conexion->prepare(
        "INSERT INTO prestamos
            (
                id_solicitud,
                id_equipo,
                id_usuario,
                fecha_entrega,
                entregado_por,
                autorizado_por,
                condicion_inicial,
                estado_prestamo
            )

         VALUES
            (
                ?,
                ?,
                ?,
                NOW(),
                ?,
                ?,
                ?,
                'Prestado'
            )"
    );

    $stmt->bind_param(
        'iiiiis',
        $idSolicitud,
        $idEquipo,
        $idSolicitante,
        $idUsuarioEntrega,
        $idAutorizador,
        $condicionInicial
    );

    $stmt->execute();

    $stmt->close();


    /*
     * Actualizar el estado del equipo:
     *
     * Reservado → Prestado
     */
    $stmt = $conexion->prepare(
        "UPDATE equipos

         SET id_estado = ?

         WHERE id_equipo = ?

         AND id_estado = (
             SELECT
                 id_estado

             FROM estados_equipo

             WHERE nombre_estado = 'Reservado'

             LIMIT 1
         )"
    );

    $stmt->bind_param(
        'ii',
        $idEstadoPrestado,
        $idEquipo
    );

    $stmt->execute();


    /*
     * Debe haberse actualizado exactamente
     * un equipo.
     */
    if (
        $stmt->affected_rows !== 1
    ) {

        $stmt->close();

        throw new RuntimeException(
            'transicion_equipo'
        );
    }

    $stmt->close();


    /*
     * Confirmar transacción.
     */
    $conexion->commit();


    /*
     * Regresar al listado.
     */
    header(
        'Location: listar.php?estado=Aprobada&ok=entrega'
    );

    exit();


} catch (Throwable $e) {

    /*
     * Revertir cualquier cambio realizado
     * durante la transacción.
     */
    $conexion->rollback();


    /*
     * Errores controlados.
     */
    $permitidos = [
        'solicitud',
        'estado',
        'autorizador',
        'equipo',
        'ya_entregado',
        'estado_prestado',
        'transicion_equipo'
    ];


    $error = in_array(
        $e->getMessage(),
        $permitidos,
        true
    )
        ? $e->getMessage()
        : 'entrega';


    header(
        'Location: listar.php?estado=Aprobada&error=' .
        urlencode($error)
    );

    exit();
}

?>