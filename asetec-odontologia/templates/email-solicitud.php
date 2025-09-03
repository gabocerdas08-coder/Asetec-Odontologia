<?php
$inicio = get_post_meta($post_id,'fecha_hora_inicio',true);
$nombre = get_post_meta($post_id,'paciente_nombre',true);
?>
<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">
  <h2 style="margin:0 0 10px">Solicitud recibida</h2>
  <p>Estimado/a <?php echo esc_html($nombre); ?>,</p>
  <p>Hemos recibido su solicitud de cita para Odontología. En cuanto sea revisada, le enviaremos la confirmación.</p>
  <p><strong>Fecha tentativa:</strong> <?php echo esc_html($inicio); ?></p>
  <p>Saludos,<br>ASETEC</p>
</div>
