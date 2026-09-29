(function(){
  'use strict';
  function isMobile(){ return window.matchMedia ? window.matchMedia('(max-width:760px)').matches : window.innerWidth <= 760; }
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
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initMenu,false); else initMenu();
})();
