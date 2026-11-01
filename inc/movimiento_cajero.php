<?php
/** Formulario compartido para depósito y retiro en ventanilla. */
function movimiento_cajero(string $sp, string $titulo, string $verbo): void {
    $u = requiere_rol('CAJERO');
    $ok = $error = null;
    if (es_post_valido()) {
        $numero = trim($_POST['numero'] ?? '');
        $monto  = trim($_POST['monto'] ?? '');
        if (!preg_match('/^\d{6,20}$/', $numero))         $error = 'Número de cuenta inválido.';
        elseif (!monto_valido($monto) || $monto <= 0)     $error = 'El monto debe ser mayor a cero.';
        else {
            try {
                $c = sp($sp, [$numero, $monto, $u['id']])[0];
                $ok = ucfirst($verbo) . ' de ' . q($monto) . " realizado en la cuenta {$c['numero']} ({$c['nombre']}). Saldo actual: " . q($c['saldo']);
                $_POST = [];
            } catch (PDOException $ex) {
                $error = error_bd($ex);
            }
        }
    }
    encabezado($titulo);
    ?>
    <div class="card angosto">
      <?php alerta($ok, $error); ?>
      <form method="post" autocomplete="off"><?= csrf_campo() ?>
        <label for="numero">No. Cuenta</label><input id="numero" name="numero" inputmode="numeric" pattern="\d{6,20}" required value="<?= e($_POST['numero'] ?? '') ?>">
        <label for="monto">Cantidad a <?= e($verbo === 'depósito' ? 'depositar' : 'debitar') ?> (Q)</label>
        <input id="monto" name="monto" type="number" min="0.01" step="0.01" required>
        <button>Realizar <?= e($verbo) ?></button>
      </form>
    </div>
    <?php
    pie();
}
