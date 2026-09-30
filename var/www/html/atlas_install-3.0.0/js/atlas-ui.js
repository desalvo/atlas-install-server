(function(){
  'use strict';
  function isMobile(){ return window.matchMedia ? window.matchMedia('(max-width:760px)').matches : window.innerWidth <= 760; }

  function initAutocompleteFallback(){
    var inputs=document.querySelectorAll('input[data-atlas-datalist]');
    for(var i=0;i<inputs.length;i++){
      (function(input){
        if(input.dataset.atlasAutocompleteBound==='1') return;
        input.dataset.atlasAutocompleteBound='1';
        var list=document.getElementById(input.getAttribute('data-atlas-datalist'));
        if(!list) return;
        var values=Array.prototype.map.call(list.querySelectorAll('option'),function(o){return o.value||o.textContent||'';}).filter(Boolean);
        var popup=document.createElement('div'); popup.className='atlas-autocomplete-popup'; popup.hidden=true; input.parentNode.appendChild(popup);
        function hide(){popup.hidden=true; popup.innerHTML='';}
        function render(){
          var q=(input.value||'').toLowerCase().trim(); popup.innerHTML='';
          var matches=values.filter(function(v){return !q || v.toLowerCase().indexOf(q)!==-1;}).slice(0,40);
          if(!matches.length){hide();return;}
          matches.forEach(function(v){var b=document.createElement('button');b.type='button';b.className='atlas-autocomplete-option';b.textContent=v;b.addEventListener('mousedown',function(e){e.preventDefault();input.value=v;hide();input.dispatchEvent(new Event('change',{bubbles:true}));});popup.appendChild(b);});
          popup.hidden=false;
        }
        input.addEventListener('input',render,false); input.addEventListener('focus',render,false); input.addEventListener('blur',function(){setTimeout(hide,120);},false); input.addEventListener('keydown',function(e){if(e.key==='Escape')hide();},false);
      })(inputs[i]);
    }
  }

  function initLegacyDefinitionSubmit(){
    // A number of legacy definition pages place <form> tags inside tables.
    // Browsers are allowed to repair that invalid markup differently, which
    // can leave Select/Save/Delete controls associated with an empty form or
    // outside the intended form entirely. Intercept these actions on the
    // affected definition pages and rebuild a deterministic POST payload from
    // the visible definition table and request parameters.
    var legacyPage=/\/(?:archdef|isdef|reldef|sitedef|taskdef|tgtdef|ispardef|pardef|sitepardef)\.php$/i.test(window.location.pathname);
    if(!legacyPage) return;

    document.addEventListener('click',function(e){
      var btn=e.target && e.target.closest ? e.target.closest('input[type="submit"],button[type="submit"]') : null;
      if(!btn) return;
      var value=(btn.value||btn.textContent||'').trim();
      if(!/^(Select|Save|Delete|Update)$/i.test(value)) return;

      // Always handle the legacy definition actions ourselves, even if the
      // browser reports btn.form: repaired table markup may point at the wrong
      // or an empty form.
      var table=document.getElementById('select_tbl');
      if(!table) return;
      e.preventDefault();
      e.stopPropagation();

      var f=document.createElement('form');
      f.method='post';
      f.action=window.location.pathname;
      f.style.display='none';

      var seen={};
      function append(name,value){
        if(!name) return;
        var h=document.createElement('input');
        h.type='hidden'; h.name=name; h.value=value == null ? '' : String(value);
        f.appendChild(h); seen[name]=true;
      }

      // Controls may have been foster-parented out of the table by the HTML
      // parser. Read from the table first, then from the page's legacy forms.
      var controls=[];
      Array.prototype.push.apply(controls,table.querySelectorAll('input,select,textarea'));
      var named=document.querySelectorAll('form[name="srcsel"] input,form[name="srcsel"] select,form[name="srcsel"] textarea,form[name$="def"] input,form[name$="def"] select,form[name$="def"] textarea');
      Array.prototype.push.apply(controls,named);
      // Finally include known source/mode controls wherever the parser moved them.
      Array.prototype.push.apply(controls,document.querySelectorAll('[name="mode"],[name="archsrc"],[name="tasksrc"],[name="relsrc"],[name="tgtsrc"],[name="sitesrc"],[name="issrc"]'));

      for(var i=0;i<controls.length;i++){
        var c=controls[i]; if(!c.name || c.disabled || seen[c.name]) continue;
        if((c.type==='checkbox'||c.type==='radio')&&!c.checked) continue;
        if(c.tagName==='SELECT' && c.multiple){
          for(var j=0;j<c.options.length;j++) if(c.options[j].selected) append(c.name,c.options[j].value);
        } else append(c.name,c.value);
      }

      var params=new URLSearchParams(window.location.search);
      ['mode','archsrc','tasksrc','relsrc','tgtsrc','sitesrc','issrc'].forEach(function(k){
        if(params.has(k) && !seen[k]) append(k,params.get(k));
      });
      append(btn.name||'submit',value);
      document.body.appendChild(f);
      f.submit();
    },true);
  }

  function initAll(){initAutocompleteFallback();initLegacyDefinitionSubmit();}
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initAll,false); else initAll();
})();
