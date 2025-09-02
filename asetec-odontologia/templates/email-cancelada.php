<?php
$inicio = get_post_meta($post_id,'fecha_hora_inicio',true);
$nombre = get_post_meta($post_id,'paciente_nombre',true);
?>
<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">
  <h2 style="margin:0 0 10px">Cita cancelada</h2>
  <p>Estimado/a <?php echo esc_html($nombre); ?>,</p>
  <p>Su cita programada para <strong><?php echo esc_html($inicio); ?></strong> fue cancelada. Si desea reprogramar, puede solicitar un nuevo horario.</p>
  <p>Saludos,<br>ASETEC</p>
</div>
