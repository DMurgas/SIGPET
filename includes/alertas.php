<?php
/**
 * Motor de alertas de vencimiento y atraso de préstamos (Incremento 4: RF-14).
 */

require_once __DIR__ . '/bitacora.php';

const DIAS_ALERTA_PROXIMO_VENCIMIENTO = 2;

/**
 * Revisa todos los préstamos activos y genera alertas persistentes para el usuario
 * solicitante y para los encargados de préstamos cuando corresponda (RF-14).
 */
function sincronizarAlertasPrestamos(mysqli $conexion, bool $registrarEnBitacora = true): array
{
    $diasVentana = DIAS_ALERTA_PROXIMO_VENCIMIENTO;
    $resumen = [
        'revisados' => 0,
        'generadas' => 0,
        'atrasados' => 0,
        'proximos' => 0
    ];

    // 1. Resolver automáticamente alertas cuyos préstamos ya fueron devueltos.
    $conexion->query(
        "UPDATE alertas a
         INNER JOIN prestamos p ON p.id_prestamo = a.id_prestamo
         SET a.resuelta = 1
         WHERE a.resuelta = 0
           AND (p.estado_prestamo = 'Devuelto' OR p.fecha_devolucion_real IS NOT NULL)"
    );

    // 2. Obtener todos los encargados de préstamos activos.
    $encargados = $conexion->query(
        "SELECT u.id_usuario
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.activo = 1
           AND r.nombre_rol = 'Encargado de préstamos'"
    )->fetch_all(MYSQLI_ASSOC);
    $idsEncargados = array_map(static fn(array $fila): int => (int)$fila['id_usuario'], $encargados);

    // 3. Revisar préstamos activos próximos a vencer o ya atrasados.
    $sqlPrestamos = "SELECT
            p.id_prestamo,
            p.id_usuario AS id_solicitante,
            p.id_equipo,
            s.fecha_devolucion_prevista,
            DATEDIFF(s.fecha_devolucion_prevista, CURDATE()) AS dias_restantes,
            u.nombres,
            u.apellidos,
            e.codigo_inventario,
            e.nombre AS nombre_equipo
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
         INNER JOIN equipos e ON e.id_equipo = p.id_equipo
         WHERE p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_devolucion_prevista <= DATE_ADD(CURDATE(), INTERVAL {$diasVentana} DAY)";

    $prestamos = $conexion->query($sqlPrestamos)->fetch_all(MYSQLI_ASSOC);
    $resumen['revisados'] = count($prestamos);

    if ($resumen['revisados'] === 0) {
        return $resumen;
    }

    $stmtExiste = $conexion->prepare(
        "SELECT id_alerta
         FROM alertas
         WHERE id_prestamo = ?
           AND id_usuario_destino = ?
           AND tipo_alerta = ?
           AND resuelta = 0
         LIMIT 1"
    );

    $stmtInsert = $conexion->prepare(
        "INSERT INTO alertas (id_prestamo, id_usuario_destino, tipo_alerta, titulo, mensaje)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmtResolverProximas = $conexion->prepare(
        "UPDATE alertas
         SET resuelta = 1
         WHERE id_prestamo = ?
           AND tipo_alerta = 'Proximo a vencer'
           AND resuelta = 0"
    );

    foreach ($prestamos as $prestamo) {
        $idPrestamo = (int)$prestamo['id_prestamo'];
        $idSolicitante = (int)$prestamo['id_solicitante'];
        $diasRestantes = (int)$prestamo['dias_restantes'];
        $equipoTexto = $prestamo['codigo_inventario'] . ' — ' . $prestamo['nombre_equipo'];
        $solicitanteTexto = trim($prestamo['nombres'] . ' ' . $prestamo['apellidos']);
        $fechaPrevista = date('d/m/Y', strtotime($prestamo['fecha_devolucion_prevista']));

        if ($diasRestantes < 0) {
            $tipoAlerta = 'Atrasado';
            $diasAtraso = abs($diasRestantes);
            $resumen['atrasados']++;
            $tituloSolicitante = 'Préstamo atrasado (' . $diasAtraso . ($diasAtraso === 1 ? ' día' : ' días') . ')';
            $mensajeSolicitante = "Tu préstamo #{$idPrestamo} del equipo {$equipoTexto} venció el {$fechaPrevista} ({$diasAtraso} día(s) de atraso). Debes devolverlo cuanto antes para regularizar tu situación.";
            $tituloEncargado = 'Préstamo atrasado — ' . $solicitanteTexto;
            $mensajeEncargado = "El préstamo #{$idPrestamo} de {$solicitanteTexto} ({$equipoTexto}) venció el {$fechaPrevista} y acumula {$diasAtraso} día(s) de atraso.";

            // Si pasó de próximo a vencer a atrasado, cerramos las alertas previas de aviso próximo.
            $stmtResolverProximas->bind_param('i', $idPrestamo);
            $stmtResolverProximas->execute();
        } else {
            $tipoAlerta = 'Proximo a vencer';
            $resumen['proximos']++;
            $cuando = $diasRestantes === 0
                ? 'vence hoy'
                : ($diasRestantes === 1 ? 'vence mañana' : "vence en {$diasRestantes} días");
            $tituloSolicitante = 'Préstamo próximo a vencer';
            $mensajeSolicitante = "Tu préstamo #{$idPrestamo} del equipo {$equipoTexto} {$cuando} ({$fechaPrevista}). Recuerda realizar la devolución a tiempo.";
            $tituloEncargado = 'Vencimiento próximo — ' . $solicitanteTexto;
            $mensajeEncargado = "El préstamo #{$idPrestamo} de {$solicitanteTexto} ({$equipoTexto}) {$cuando} ({$fechaPrevista}).";
        }

        // Destinatarios: solicitante + todos los encargados de préstamos activos.
        $destinatarios = [$idSolicitante => [$tituloSolicitante, $mensajeSolicitante]];
        foreach ($idsEncargados as $idEncargado) {
            if (!isset($destinatarios[$idEncargado])) {
                $destinatarios[$idEncargado] = [$tituloEncargado, $mensajeEncargado];
            }
        }

        foreach ($destinatarios as $idDestino => $contenido) {
            [$titulo, $mensaje] = $contenido;
            $stmtExiste->bind_param('iis', $idPrestamo, $idDestino, $tipoAlerta);
            $stmtExiste->execute();
            $yaExiste = $stmtExiste->get_result()->num_rows > 0;

            if (!$yaExiste) {
                $stmtInsert->bind_param('iisss', $idPrestamo, $idDestino, $tipoAlerta, $titulo, $mensaje);
                $stmtInsert->execute();
                $idAlerta = (int)$conexion->insert_id;
                $resumen['generadas']++;

                if ($registrarEnBitacora) {
                    registrarBitacora(
                        $conexion,
                        null,
                        'Alertas',
                        'Generación de alerta',
                        'alertas',
                        $idAlerta,
                        "Alerta '{$tipoAlerta}' generada para préstamo #{$idPrestamo} ({$equipoTexto}) dirigida al usuario #{$idDestino}."
                    );
                }
            }
        }
    }

    $stmtExiste->close();
    $stmtInsert->close();
    $stmtResolverProximas->close();

    return $resumen;
}

/**
 * Cuenta las alertas activas no leídas de un usuario para el indicador de la barra lateral.
 */
function contarAlertasNoLeidasUsuario(mysqli $conexion, int $idUsuario): int
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM alertas
         WHERE id_usuario_destino = ?
           AND resuelta = 0
           AND leida = 0"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total;
}
