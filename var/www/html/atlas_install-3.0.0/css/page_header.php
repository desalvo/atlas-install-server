<?php
function page_header($path=".") {
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <link rel="icon" href="<?php echo $path ?>/img/favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="<?php echo $path ?>/img/ljsf3-icon.png">
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/ljsf.css">
  <link href="<?php echo $path ?>/css/dropdown/dropdown.css" media="screen" rel="stylesheet" type="text/css" />
  <link href="<?php echo $path ?>/css/dropdown/themes/ljsf.css" media="screen" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/modern.css">
  <script type="text/javascript" src="<?php echo $path ?>/js/jquery-1.9.1.min.js"></script>
  <script type="text/javascript" src="<?php echo $path ?>/js/atlas-ui.js" defer></script>

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
    var atlasI18n = {recordsPerPage: <?php echo json_encode(atlas_t('records_per_page')); ?>, all: <?php echo json_encode(atlas_t('all')); ?>, previous: <?php echo json_encode(atlas_t('previous')); ?>, next: <?php echo json_encode(atlas_t('next')); ?>, page: <?php echo json_encode(atlas_t('page')); ?>, of: <?php echo json_encode(atlas_t('of')); ?>, records: <?php echo json_encode(atlas_t('records')); ?>, details: <?php echo json_encode(atlas_t('details')); ?>, hideDetails: <?php echo json_encode(atlas_t('hide_details')); ?>};
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

      // Mobile wide-table adaptation: keep desktop tables unchanged, but on narrow
      // screens present each data row as a compact collapsible record.
      (function enableMobileRows(){
        if (table.dataset.noMobileCollapse === '1' || table.id === 'select_tbl' || table.id === 'toolbar_tbl' || table.classList.contains('ui-datepicker-calendar')) return;
        var allRows = Array.prototype.slice.call(table.querySelectorAll('tr'));
        if (allRows.length < 2) return;
        var headerRow = null;
        for (var hr=0; hr<allRows.length; hr++) { if (allRows[hr].querySelectorAll('th').length >= 3) { headerRow=allRows[hr]; break; } }
        if (!headerRow) return;
        var headers = Array.prototype.slice.call(headerRow.children).map(function(c,i){ return (c.dataset && c.dataset.columnLabel ? c.dataset.columnLabel : (c.textContent||'')).trim() || ('#'+(i+1)); });
        if (headers.length < 4) return;
        var data = allRows.filter(function(r){ if(table.id==='atlas-list-results') return r.hasAttribute('data-atlas-list-record'); return r!==headerRow && r.querySelectorAll('td').length >= 4; });
        if (!data.length) return;
        var controlCount=table.querySelectorAll('input,select,textarea').length;
        if (controlCount > data.length * 2) return; // forms/search matrices are not data tables
        var preferred=[];
        var explicitPrimary=Array.prototype.slice.call(headerRow.children).map(function(c,i){return c.dataset.mobilePrimary==='1'?i:-1;}).filter(function(i){return i>=0;});
        if(explicitPrimary.length) preferred=explicitPrimary.slice(0,6);
        else {
          var priority=/^(num|id|ref|release(?:\s*number)?|release\s*num|name|nome|site(?:\s*name)?|sito|release\s*arch|architecture|architettura|status|stato|date|data|task|request|richiesta|resource|risorsa)$/i;
          headers.forEach(function(h,i){ if(priority.test(h.replace(/\s+/g,' ').trim()) && preferred.length<4) preferred.push(i); });
          for(var pi=0; preferred.length<3 && pi<headers.length; pi++) if(preferred.indexOf(pi)<0) preferred.push(pi);
        }
        data.forEach(function(row){
          var cells=Array.prototype.slice.call(row.children);
          row.classList.add('atlas-mobile-record'); row.setAttribute('aria-expanded','false'); row.setAttribute('tabindex','0');
          cells.forEach(function(cell,i){
            cell.setAttribute('data-label',headers[i]||('#'+(i+1)));
            if(cell.dataset.mobileHidden==='1' || cell.querySelector('input[type=hidden]')) cell.classList.add('atlas-mobile-hidden');
            if(cell.dataset.mobilePrimary==='1' || preferred.indexOf(i)>=0) cell.classList.add('atlas-mobile-primary');
          });
          var first=cells[preferred[0]||0] || cells[0];
          var b=null;
          if(first && !first.querySelector('.atlas-row-toggle')){
            b=document.createElement('button'); b.type='button'; b.className='atlas-row-toggle'; b.setAttribute('aria-expanded','false'); if(table.id==='atlas-list-results'){b.textContent='▸';b.setAttribute('aria-label',atlasI18n.details);first.insertBefore(b,first.firstChild);}else{b.textContent=atlasI18n.details;first.appendChild(b);}
          } else if(first) b=first.querySelector('.atlas-row-toggle');
          function toggleRow(e){
            if(e && e.target && e.target.closest && e.target.closest('a,button,input,select,textarea,label')) return;
            var open=row.classList.toggle('atlas-mobile-expanded'); row.setAttribute('aria-expanded',open?'true':'false'); if(b){b.setAttribute('aria-expanded',open?'true':'false');b.textContent=table.id==='atlas-list-results'?(open?'▾':'▸'):(open?atlasI18n.hideDetails:atlasI18n.details);}
          }
          if(b) b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var open=row.classList.toggle('atlas-mobile-expanded');row.setAttribute('aria-expanded',open?'true':'false');b.setAttribute('aria-expanded',open?'true':'false');b.textContent=table.id==='atlas-list-results'?(open?'▾':'▸'):(open?atlasI18n.hideDetails:atlasI18n.details);},false);
          row.addEventListener('click',toggleRow,false);
          row.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggleRow();}},false);
        });
        table.classList.add('atlas-mobile-collapsible-table');
      })();

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
      label.textContent = atlasI18n.recordsPerPage + ': ';
      var select = document.createElement('select');
      select.className = 'atlas-per-page';
      [['50','50'],['100','100'],['200','200'],['500','500'],['1000','1000'],['all',atlasI18n.all]].forEach(function(opt){
        var o=document.createElement('option'); o.value=opt[0]; o.textContent=opt[1]; if(opt[0]===requested)o.selected=true; select.appendChild(o);
      });
      label.appendChild(select); controls.appendChild(label);
      var status=document.createElement('span'); status.className='atlas-pagination-status'; controls.appendChild(status);
      var prev=document.createElement('button'); prev.type='button'; prev.textContent='‹ '+atlasI18n.previous;
      var next=document.createElement('button'); next.type='button'; next.textContent=atlasI18n.next+' ›';
      controls.appendChild(prev); controls.appendChild(next);
      wrapper.parentNode.insertBefore(controls, wrapper);

      function render(){
        pages=Math.max(1,Math.ceil(dataRows.length/perPage)); page=Math.min(page,pages);
        var start=(page-1)*perPage, end=Math.min(dataRows.length,start+perPage);
        dataRows.forEach(function(r,i){r.style.display=(i>=start&&i<end)?'':'none';});
        status.textContent = requested==='all' ? dataRows.length+' '+atlasI18n.records : atlasI18n.page+' '+page+' '+atlasI18n.of+' '+pages+' · '+(start+1)+'–'+end+' '+atlasI18n.of+' '+dataRows.length;
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
