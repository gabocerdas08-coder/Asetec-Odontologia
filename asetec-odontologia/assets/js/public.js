(function($){
  $(function(){
    $('#chk_fam').on('change', function(){ $('#fam_fields').toggle( this.checked ); });

    $('#odo_buscar').on('click', function(){
      var d = $('#odo_fecha').val();
      if(!d) { alert('Seleccione una fecha'); return; }
      $('#odo_slots').html('<p>Cargando...</p>');
      $.post(ASETEC_ODO.ajax, { action:'asetec_odo_get_slots', nonce:ASETEC_ODO.nonce, date:d }, function(res){
        if(!res.success){ $('#odo_slots').html('<p>'+ (res.data && res.data.msg ? res.data.msg : 'Error') +'</p>'); return; }
        var html = '<ul class="odo-slot-list">';
        if(!res.data.slots || !res.data.slots.length){ html += '<li>No hay horarios disponibles.</li>'; }
        else {
          res.data.slots.forEach(function(s){
            html += '<li><button class="button odo-choose" data-start="'+s.start+'" data-end="'+s.end+'">'+s.start.substr(11,5)+' - '+s.end.substr(11,5)+'</button></li>';
          });
        }
        html += '</ul>';
        $('#odo_slots').html(html);
      });
    });

    $(document).on('click', '.odo-choose', function(){
      $('#odo_start').val( $(this).data('start') );
      $('#odo_end').val( $(this).data('end') );
      $('#odo_form').slideDown();
    });

    $('#odo_submit').on('click', function(e){
      e.preventDefault();
      var form = $('#odo_form');
      var data = form.serializeArray();
      data.push({name:'action', value:'asetec_odo_submit_request'});
      data.push({name:'nonce', value:ASETEC_ODO.nonce});
      $.post(ASETEC_ODO.ajax, data, function(res){
        if(!res.success){ alert(res.data && res.data.msg ? res.data.msg : 'Error'); return; }
        alert(res.data.msg);
        location.reload();
      });
    });
  });
})(jQuery);

document.addEventListener('DOMContentLoaded', function(){
  const chkFam = document.getElementById('chk_fam');
  const famFields = document.getElementById('fam_fields');
  if (chkFam && famFields) {
    chkFam.addEventListener('change', function(){
      famFields.style.display = chkFam.checked ? 'block' : 'none';
    });
  }
  // Puedes agregar más mejoras visuales aquí (validaciones, feedback, etc)
});
