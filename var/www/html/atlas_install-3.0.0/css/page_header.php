<?php
function page_header($path=".") {
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <link rel="shortcut icon" href="<?php echo $path ?>/img/favicon.ico">
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/ljsf.css">
  <link href="<?php echo $path ?>/css/dropdown/dropdown.css" media="screen" rel="stylesheet" type="text/css" />
  <link href="<?php echo $path ?>/css/dropdown/themes/ljsf.css" media="screen" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/modern.css">
  <script type="text/javascript" src="<?php echo $path ?>/js/jquery-1.9.1.min.js"></script>

  <script type="text/javascript">
    $(function() {
      var moveLeft = 300;
      var moveDown = 10;
      $('a#trigger').hover(function() {
        $('div#help').show();
      }, function() {
        $('div#help').hide();
      });
      $('a#trigger').mousemove(function(e) {
        $('div#help').css('top', e.pageY + moveDown).css('left', e.pageX - moveLeft);
      });
    });
  </script>
  <script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    var allowed = ['50','100','200','500','1000','all'];
    var params = new URLSearchParams(window.location.search);
    var requested = (params.get('per_page') || '200').toLowerCase();
    if (allowed.indexOf(requested) === -1) requested = '200';

    document.querySelectorAll('#content table, #site_content table').forEach(function (table, tableIndex) {
      if (table.closest('.atlas-table-scroll') || table.classList.contains('ui-datepicker-calendar')) return;
      var wrapper = document.createElement('div');
      wrapper.className = 'atlas-table-scroll';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);

      if (table.dataset.noPagination === '1' || table.id === 'select_tbl' || table.id === 'toolbar_tbl') return;
      var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
      if (rows.length <= 201) return;
      var firstData = 0;
      while (firstData < rows.length && rows[firstData].querySelector('th')) firstData++;
      if (firstData === 0) firstData = 1; // preserve the first legacy heading row
      var dataRows = rows.slice(firstData);
      if (dataRows.length <= 200) return;

      var perPage = requested === 'all' ? dataRows.length : parseInt(requested, 10);
      var pages = Math.max(1, Math.ceil(dataRows.length / perPage));
      var page = Math.min(pages, Math.max(1, parseInt(params.get('page') || '1', 10) || 1));
      var controls = document.createElement('div');
      controls.className = 'atlas-pagination';
      controls.setAttribute('aria-label','Paginazione tabella');
      var label = document.createElement('label');
      label.textContent = 'Record per pagina: ';
      var select = document.createElement('select');
      select.className = 'atlas-per-page';
      [['50','50'],['100','100'],['200','200'],['500','500'],['1000','1000'],['all','Tutti']].forEach(function(opt){
        var o=document.createElement('option'); o.value=opt[0]; o.textContent=opt[1]; if(opt[0]===requested)o.selected=true; select.appendChild(o);
      });
      label.appendChild(select); controls.appendChild(label);
      var status=document.createElement('span'); status.className='atlas-pagination-status'; controls.appendChild(status);
      var prev=document.createElement('button'); prev.type='button'; prev.textContent='‹ Precedente';
      var next=document.createElement('button'); next.type='button'; next.textContent='Successiva ›';
      controls.appendChild(prev); controls.appendChild(next);
      wrapper.parentNode.insertBefore(controls, wrapper);

      function render(){
        pages=Math.max(1,Math.ceil(dataRows.length/perPage)); page=Math.min(page,pages);
        var start=(page-1)*perPage, end=Math.min(dataRows.length,start+perPage);
        dataRows.forEach(function(r,i){r.style.display=(i>=start&&i<end)?'':'none';});
        status.textContent = requested==='all' ? dataRows.length+' record' : 'Pagina '+page+' di '+pages+' · '+(start+1)+'–'+end+' di '+dataRows.length;
        prev.disabled=page<=1; next.disabled=page>=pages;
      }
      prev.addEventListener('click',function(){if(page>1){page--;render();controls.scrollIntoView({block:'nearest'});}});
      next.addEventListener('click',function(){if(page<pages){page++;render();controls.scrollIntoView({block:'nearest'});}});
      select.addEventListener('change',function(){
        var u=new URL(window.location.href); u.searchParams.set('per_page',select.value); u.searchParams.delete('page'); window.location.assign(u.toString());
      });
      render();
    });
  });
  </script>
<?php } ?>
