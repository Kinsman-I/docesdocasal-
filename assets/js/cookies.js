(function(){
  const KEY='ddc_cookie_consent_v1';
  const banner=document.getElementById('ddcCookieBanner');
  const modal=document.getElementById('ddcCookieModal');
  const analytics=document.getElementById('ddcCookieAnalytics');
  const marketing=document.getElementById('ddcCookieMarketing');

  function load(){
    try{return JSON.parse(localStorage.getItem(KEY)||'null')}catch(e){return null}
  }

  function apply(c){
    window.dataLayer=window.dataLayer||[];
    window.gtag=window.gtag||function(){window.dataLayer.push(arguments)};
    window.gtag('consent','update',{
      analytics_storage:c.analytics?'granted':'denied',
      ad_storage:c.marketing?'granted':'denied',
      ad_user_data:c.marketing?'granted':'denied',
      ad_personalization:c.marketing?'granted':'denied',
      functionality_storage:'granted',
      security_storage:'granted'
    });
  }

  function save(c){
    localStorage.setItem(KEY,JSON.stringify({
      essential:true,
      analytics:!!c.analytics,
      marketing:!!c.marketing,
      saved_at:new Date().toISOString()
    }));
    apply(c);
    banner?.classList.remove('show');
    modal?.classList.remove('open');
  }

  const current=load();
  if(current){
    apply(current);
  }else{
    banner?.classList.add('show');
  }

  document.querySelectorAll('[data-ddc-cookie-accept]').forEach(el=>{
    el.addEventListener('click',()=>save({analytics:true,marketing:true}));
  });

  document.querySelectorAll('[data-ddc-cookie-reject]').forEach(el=>{
    el.addEventListener('click',()=>save({analytics:false,marketing:false}));
  });

  document.querySelectorAll('[data-ddc-cookie-settings]').forEach(el=>{
    el.addEventListener('click',()=>{
      const c=load()||{analytics:false,marketing:false};
      if(analytics)analytics.checked=!!c.analytics;
      if(marketing)marketing.checked=!!c.marketing;
      modal?.classList.add('open');
    });
  });

  document.querySelectorAll('[data-ddc-cookie-close]').forEach(el=>{
    el.addEventListener('click',()=>modal?.classList.remove('open'));
  });

  document.querySelectorAll('[data-ddc-cookie-save]').forEach(el=>{
    el.addEventListener('click',()=>save({
      analytics:!!analytics?.checked,
      marketing:!!marketing?.checked
    }));
  });
})();
