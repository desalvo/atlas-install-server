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

  function initMenu(){
    var bar=document.getElementById('menubar');
    var toggle=document.getElementById('atlas-mobile-menu-toggle');
    var close=document.getElementById('atlas-mobile-menu-close');
    var backdrop=document.getElementById('atlas-mobile-menu-backdrop');
    if(!bar||!toggle||toggle.dataset.atlasBound==='1') return;
    toggle.dataset.atlasBound='1';
    function setOpen(open){
      bar.classList.toggle('atlas-mobile-open',!!open);
      toggle.setAttribute('aria-expanded',open?'true':'false');
      if(backdrop){backdrop.hidden=!open;backdrop.classList.toggle('is-open',!!open);}
      document.documentElement.classList.toggle('atlas-menu-open',!!open);
    }
    window.atlasSetMobileMenuOpen=setOpen;
    window.atlasToggleMobileMenu=function(ev){ if(ev){ev.preventDefault();ev.stopPropagation();} setOpen(!bar.classList.contains('atlas-mobile-open')); return false; };
    toggle.addEventListener('click',window.atlasToggleMobileMenu,false);
    if(close) close.addEventListener('click',function(e){e.preventDefault();setOpen(false);},false);
    if(backdrop) backdrop.addEventListener('click',function(){setOpen(false);},false);
    document.addEventListener('keydown',function(e){if(e.key==='Escape')setOpen(false);},false);
    var dirs=bar.querySelectorAll('#menu > li > a.dir');
    for(var i=0;i<dirs.length;i++) dirs[i].addEventListener('click',function(e){
      if(!isMobile()) return;
      var li=this.parentElement, submenu=null;
      for(var c=0;c<li.children.length;c++){ if(li.children[c].tagName==='UL'){submenu=li.children[c];break;} }
      if(!submenu) return;
      e.preventDefault();
      var opened=bar.querySelectorAll('#menu > li.atlas-mobile-section-open');
      for(var j=0;j<opened.length;j++) if(opened[j]!==li) opened[j].classList.remove('atlas-mobile-section-open');
      li.classList.toggle('atlas-mobile-section-open');
    },false);
    var links=bar.querySelectorAll('#menu ul a, #menu > li.atlas-menu-single > a:not(#trigger)');
    for(var k=0;k<links.length;k++) links[k].addEventListener('click',function(){if(isMobile())setOpen(false);},false);
    window.addEventListener('resize',function(){if(!isMobile())setOpen(false);},false);
  }
  function initAll(){initMenu();initAutocompleteFallback();}
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initAll,false); else initAll();
})();
