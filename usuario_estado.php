<?php
require 'inc/config.php';
require 'inc/layout.php';
$u = requiere_rol('CLIENTE');

$info = sp('sp_cliente_info', [$u['id']])[0];
$movs = sp('sp_estado_cuenta', [$u['id']]);

encabezado('Estado de cuenta');
?>
<div class="grid">
  <div class="kpi"><span>Cuenta</span><b><?= e($info['numero']) ?></b><?= e($info['nombre']) ?></div>
  <div class="kpi"><span>Saldo actual</span><b><?= q($info['saldo']) ?></b></div>
</div>
<div class="card tabla" style="margin-top:1rem">
  <table>
    <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Cuenta relacionada</th><th class="num">Crédito</th><th class="num">Débito</th><th class="num">Saldo</th></tr>
    <?php $saldo = 0; foreach ($movs as $m): $saldo += $m['credito'] - $m['debito']; ?>
    <tr>
      <td><?= e($m['fecha']) ?></td><td><?= e($m['tipo']) ?></td><td><?= e($m['descripcion']) ?></td><td><?= e($m['contraparte'] ?? '-') ?></td>
      <td class="num cred"><?= $m['credito'] > 0 ? q($m['credito']) : '' ?></td>
      <td class="num deb"><?= $m['debito'] > 0 ? q($m['debito']) : '' ?></td>
      <td class="num"><?= q($saldo) ?></td>
    </tr>
    <?php endforeach; if (!$movs): ?><tr><td colspan="7">Sin movimientos.</td></tr><?php endif; ?>
  </table>
</div>
<?php pie();
