<?php
require 'inc/config.php';
require 'inc/layout.php';
requiere_rol('ADMIN');

$r = sp('sp_monitor_resumen')[0];
encabezado('Monitor de transferencias');
?>
<div class="grid">
  <div class="kpi"><span>Cuentas creadas hoy</span><b><?= (int)$r['cuentas_hoy'] ?></b></div>
  <div class="kpi"><span>Usuarios cliente registrados hoy</span><b><?= (int)$r['clientes_hoy'] ?></b></div>
  <div class="kpi"><span>Transacciones</span><b><?= (int)$r['transacciones'] ?></b></div>
  <div class="kpi"><span>Depósitos</span><b><?= (int)$r['depositos'] ?></b></div>
  <div class="kpi"><span>Retiros</span><b><?= (int)$r['retiros'] ?></b></div>
</div>
<div class="card" style="margin-top:1rem">
  <h2>Depósitos vs. retiros (monto)</h2>
  <p><?= q($r['monto_depositos']) ?> en depósitos · <?= q($r['monto_retiros']) ?> en retiros</p>
  <div style="position:relative;height:300px"><canvas id="grafica"></canvas></div>
</div>
<?php
$datos = json_encode([(float)$r['monto_depositos'], (float)$r['monto_retiros']]);
pie(<<<HTML
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('grafica'), {
  type: 'bar',
  data: { labels: ['Depósitos', 'Retiros'],
          datasets: [{ label: 'Monto (Q)', data: $datos, backgroundColor: ['#1e7d4f', '#b3261e'] }] },
  options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
             scales: { y: { beginAtZero: true } } }
});
</script>
HTML);
