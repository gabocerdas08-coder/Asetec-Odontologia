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
```

**`templates/email-aprobada.php`**
```php
<?php
$inicio = get_post_meta($post_id,'fecha_hora_inicio',true);
$fin    = get_post_meta($post_id,'fecha_hora_fin',true);
$nombre = get_post_meta($post_id,'paciente_nombre',true);
?>
<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">
  <h2 style="margin:0 0 10px">¡Su cita fue aprobada!</h2>
  <p>Estimado/a <?php echo esc_html($nombre); ?>,</p>
  <p>Confirmamos su cita de Odontología.</p>
  <ul>
    <li><strong>Inicio:</strong> <?php echo esc_html($inicio); ?></li>
    <li><strong>Fin:</strong> <?php echo esc_html($fin); ?></li>
  </ul>
  <p>Adjuntamos un archivo .ics para agregar a su calendario.</p>
  <p>Saludos,<br>ASETEC</p>
</div>
```

**`templates/email-cancelada.php`**
```php
<?php
$inicio = get_post_meta($post_id,'fecha_hora_inicio',true);
$nombre = get_post_meta($post_id,'paciente_nombre',true);
?>
<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">
  <h2 style="margin:0 0 10px">Cita cancelada</h2>
  <p>Estimado/a <?php echo esc_html($nombre); ?>,</p>
  <p>Su cita programada para <strong><?php echo esc_html($inicio); ?></strong> fue cancelada. Si desea reprogramar, puede solicitar un nuevo horario.</p>
  <p>Saludos,<br>ASETEC</p>