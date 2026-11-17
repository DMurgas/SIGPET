<?php
/**
 * Registro centralizado de bitácora de acciones relevantes (RF-18 / RNF-09).
 */
function registrarBitacora(
    mysqli $conexion,
    ?int $idUsuario,
    string $modulo,
    string $accion,
    string $entidad,
    ?int $idEntidad,
    string $detalle
): void {
    $moduloCorto = mb_substr($modulo, 0, 50);
    $accionCorta = mb_substr($accion, 0, 80);
    $entidadCorta = mb_substr($entidad, 0, 50);
    $detalleCorto = mb_substr($detalle, 0, 1000);

    try {
        $stmt = $conexion->prepare(
            "INSERT INTO bitacora (id_usuario, modulo, accion, entidad, id_entidad, detalle)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param(
            'isssis',
            $idUsuario,
            $moduloCorto,
            $accionCorta,
            $entidadCorta,
            $idEntidad,
            $detalleCorto
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Evita interrumpir operaciones críticas si existiera algún fallo auxiliar de registro.
    }
}
