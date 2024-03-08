import{A as C,o as b,u as Y,t as z,l as Me,i as P,N as Ee,a as et,s as tt,S as W,b as lt,T as at}from"./keyboard.58689cfa.js";import{c as Ne,w as nt,i as oe,f as Se,s as Te,S as V,t as je,P as ce,N as I,a as Re,T as rt,b as ot,o as fe,d as it}from"./disposables.be045d92.js";import{q as k,g as p,s as F,j as f,h as T,x,y as E,F as st,z as X,A as ut,B as _,C as O,u as N,p as dt,D as ct,E as ft,G as pt,n as vt,o as Ie,f as mt,b as R,c as gt,w as Z,a as J,r as ke}from"./app.c4e47028.js";function We(e,t,l,a){Ne.isServer||k(n=>{e=e!=null?e:window,e.addEventListener(t,l,a),n(()=>e.removeEventListener(t,l,a))})}var re=(e=>(e[e.Forwards=0]="Forwards",e[e.Backwards=1]="Backwards",e))(re||{});function ht(){let e=p(0);return nt("keydown",t=>{t.key==="Tab"&&(e.value=t.shiftKey?1:0)}),e}function Ue(e){if(!e)return new Set;if(typeof e=="function")return new Set(e());let t=new Set;for(let l of e.value){let a=b(l);a instanceof HTMLElement&&t.add(a)}return t}var Ve=(e=>(e[e.None=1]="None",e[e.InitialFocus=2]="InitialFocus",e[e.TabLock=4]="TabLock",e[e.FocusLock=8]="FocusLock",e[e.RestoreFocus=16]="RestoreFocus",e[e.All=30]="All",e))(Ve||{});let ae=Object.assign(F({name:"FocusTrap",props:{as:{type:[Object,String],default:"div"},initialFocus:{type:Object,default:null},features:{type:Number,default:30},containers:{type:[Object,Function],default:p(new Set)}},inheritAttrs:!1,setup(e,{attrs:t,slots:l,expose:a}){let n=p(null);a({el:n,$el:n});let r=f(()=>oe(n)),o=p(!1);T(()=>o.value=!0),x(()=>o.value=!1),bt({ownerDocument:r},f(()=>o.value&&Boolean(e.features&16)));let i=wt({ownerDocument:r,container:n,initialFocus:f(()=>e.initialFocus)},f(()=>o.value&&Boolean(e.features&2)));Et({ownerDocument:r,container:n,containers:e.containers,previousActiveElement:i},f(()=>o.value&&Boolean(e.features&8)));let s=ht();function d(h){let m=b(n);!m||(w=>w())(()=>{Y(s.value,{[re.Forwards]:()=>{ce(m,I.First,{skipElements:[h.relatedTarget]})},[re.Backwards]:()=>{ce(m,I.Last,{skipElements:[h.relatedTarget]})}})})}let u=p(!1);function g(h){h.key==="Tab"&&(u.value=!0,requestAnimationFrame(()=>{u.value=!1}))}function c(h){if(!o.value)return;let m=Ue(e.containers);b(n)instanceof HTMLElement&&m.add(b(n));let w=h.relatedTarget;w instanceof HTMLElement&&w.dataset.headlessuiFocusGuard!=="true"&&(Ye(m,w)||(u.value?ce(b(n),Y(s.value,{[re.Forwards]:()=>I.Next,[re.Backwards]:()=>I.Previous})|I.WrapAround,{relativeTo:h.target}):h.target instanceof HTMLElement&&V(h.target)))}return()=>{let h={},m={ref:n,onKeydown:g,onFocusout:c},{features:w,initialFocus:S,containers:q,...A}=e;return E(st,[Boolean(w&4)&&E(Se,{as:"button",type:"button","data-headlessui-focus-guard":!0,onFocus:d,features:Te.Focusable}),C({ourProps:m,theirProps:{...t,...A},slot:h,attrs:t,slots:l,name:"FocusTrap"}),Boolean(w&4)&&E(Se,{as:"button",type:"button","data-headlessui-focus-guard":!0,onFocus:d,features:Te.Focusable})])}}}),{features:Ve});function yt(e){let t=p(Re.slice());return X([e],([l],[a])=>{a===!0&&l===!1?je(()=>{t.value.splice(0)}):a===!1&&l===!0&&(t.value=Re.slice())},{flush:"post"}),()=>{var l;return(l=t.value.find(a=>a!=null&&a.isConnected))!=null?l:null}}function bt({ownerDocument:e},t){let l=yt(t);T(()=>{k(()=>{var a,n;t.value||((a=e.value)==null?void 0:a.activeElement)===((n=e.value)==null?void 0:n.body)&&V(l())},{flush:"post"})}),x(()=>{t.value&&V(l())})}function wt({ownerDocument:e,container:t,initialFocus:l},a){let n=p(null),r=p(!1);return T(()=>r.value=!0),x(()=>r.value=!1),T(()=>{X([t,l,a],(o,i)=>{if(o.every((d,u)=>(i==null?void 0:i[u])===d)||!a.value)return;let s=b(t);s&&je(()=>{var d,u;if(!r.value)return;let g=b(l),c=(d=e.value)==null?void 0:d.activeElement;if(g){if(g===c){n.value=c;return}}else if(s.contains(c)){n.value=c;return}g?V(g):ce(s,I.First|I.NoScroll)===rt.Error&&console.warn("There are no focusable elements inside the <FocusTrap />"),n.value=(u=e.value)==null?void 0:u.activeElement})},{immediate:!0,flush:"post"})}),n}function Et({ownerDocument:e,container:t,containers:l,previousActiveElement:a},n){var r;We((r=e.value)==null?void 0:r.defaultView,"focus",o=>{if(!n.value)return;let i=Ue(l);b(t)instanceof HTMLElement&&i.add(b(t));let s=a.value;if(!s)return;let d=o.target;d&&d instanceof HTMLElement?Ye(i,d)?(a.value=d,V(d)):(o.preventDefault(),o.stopPropagation(),V(s)):V(a.value)},!0)}function Ye(e,t){for(let l of e)if(l.contains(t))return!0;return!1}function St(e){let t=ut(e.getSnapshot());return x(e.subscribe(()=>{t.value=e.getSnapshot()})),t}function Tt(e,t){let l=e(),a=new Set;return{getSnapshot(){return l},subscribe(n){return a.add(n),()=>a.delete(n)},dispatch(n,...r){let o=t[n].call(l,...r);o&&(l=o,a.forEach(i=>i()))}}}function $t(){let e;return{before({doc:t}){var l;let a=t.documentElement;e=((l=t.defaultView)!=null?l:window).innerWidth-a.clientWidth},after({doc:t,d:l}){let a=t.documentElement,n=a.clientWidth-a.offsetWidth,r=e-n;l.style(a,"paddingRight",`${r}px`)}}}function Lt(){return ot()?{before({doc:e,d:t,meta:l}){function a(n){return l.containers.flatMap(r=>r()).some(r=>r.contains(n))}t.microTask(()=>{var n;if(window.getComputedStyle(e.documentElement).scrollBehavior!=="auto"){let i=fe();i.style(e.documentElement,"scrollBehavior","auto"),t.add(()=>t.microTask(()=>i.dispose()))}let r=(n=window.scrollY)!=null?n:window.pageYOffset,o=null;t.addEventListener(e,"click",i=>{if(i.target instanceof HTMLElement)try{let s=i.target.closest("a");if(!s)return;let{hash:d}=new URL(s.href),u=e.querySelector(d);u&&!a(u)&&(o=u)}catch{}},!0),t.addEventListener(e,"touchstart",i=>{if(i.target instanceof HTMLElement)if(a(i.target)){let s=i.target;for(;s.parentElement&&a(s.parentElement);)s=s.parentElement;t.style(s,"overscrollBehavior","contain")}else t.style(i.target,"touchAction","none")}),t.addEventListener(e,"touchmove",i=>{if(i.target instanceof HTMLElement)if(a(i.target)){let s=i.target;for(;s.parentElement&&s.dataset.headlessuiPortal!==""&&!(s.scrollHeight>s.clientHeight||s.scrollWidth>s.clientWidth);)s=s.parentElement;s.dataset.headlessuiPortal===""&&i.preventDefault()}else i.preventDefault()},{passive:!1}),t.add(()=>{var i;let s=(i=window.scrollY)!=null?i:window.pageYOffset;r!==s&&window.scrollTo(0,r),o&&o.isConnected&&(o.scrollIntoView({block:"nearest"}),o=null)})})}}:{}}function Ft(){return{before({doc:e,d:t}){t.style(e.documentElement,"overflow","hidden")}}}function Pt(e){let t={};for(let l of e)Object.assign(t,l(t));return t}let U=Tt(()=>new Map,{PUSH(e,t){var l;let a=(l=this.get(e))!=null?l:{doc:e,count:0,d:fe(),meta:new Set};return a.count++,a.meta.add(t),this.set(e,a),this},POP(e,t){let l=this.get(e);return l&&(l.count--,l.meta.delete(t)),this},SCROLL_PREVENT({doc:e,d:t,meta:l}){let a={doc:e,d:t,meta:Pt(l)},n=[Lt(),$t(),Ft()];n.forEach(({before:r})=>r==null?void 0:r(a)),n.forEach(({after:r})=>r==null?void 0:r(a))},SCROLL_ALLOW({d:e}){e.dispose()},TEARDOWN({doc:e}){this.delete(e)}});U.subscribe(()=>{let e=U.getSnapshot(),t=new Map;for(let[l]of e)t.set(l,l.documentElement.style.overflow);for(let l of e.values()){let a=t.get(l.doc)==="hidden",n=l.count!==0;(n&&!a||!n&&a)&&U.dispatch(l.count>0?"SCROLL_PREVENT":"SCROLL_ALLOW",l),l.count===0&&U.dispatch("TEARDOWN",l)}});function Ct(e,t,l){let a=St(U),n=f(()=>{let r=e.value?a.value.get(e.value):void 0;return r?r.count>0:!1});return X([e,t],([r,o],[i],s)=>{if(!r||!o)return;U.dispatch("PUSH",r,l);let d=!1;s(()=>{d||(U.dispatch("POP",i!=null?i:r,l),d=!0)})},{immediate:!0}),n}let be=new Map,ne=new Map;function _e(e,t=p(!0)){k(l=>{var a;if(!t.value)return;let n=b(e);if(!n)return;l(function(){var o;if(!n)return;let i=(o=ne.get(n))!=null?o:1;if(i===1?ne.delete(n):ne.set(n,i-1),i!==1)return;let s=be.get(n);s&&(s["aria-hidden"]===null?n.removeAttribute("aria-hidden"):n.setAttribute("aria-hidden",s["aria-hidden"]),n.inert=s.inert,be.delete(n))});let r=(a=ne.get(n))!=null?a:0;ne.set(n,r+1),r===0&&(be.set(n,{"aria-hidden":n.getAttribute("aria-hidden"),inert:n.inert}),n.setAttribute("aria-hidden","true"),n.inert=!0)})}function Dt({defaultContainers:e=[],portals:t,mainTreeNodeRef:l}={}){let a=p(null),n=oe(a);function r(){var o,i,s;let d=[];for(let u of e)u!==null&&(u instanceof HTMLElement?d.push(u):"value"in u&&u.value instanceof HTMLElement&&d.push(u.value));if(t!=null&&t.value)for(let u of t.value)d.push(u);for(let u of(o=n==null?void 0:n.querySelectorAll("html > *, body > *"))!=null?o:[])u!==document.body&&u!==document.head&&u instanceof HTMLElement&&u.id!=="headlessui-portal-root"&&(u.contains(b(a))||u.contains((s=(i=b(a))==null?void 0:i.getRootNode())==null?void 0:s.host)||d.some(g=>u.contains(g))||d.push(u));return d}return{resolveContainers:r,contains(o){return r().some(i=>i.contains(o))},mainTreeNodeRef:a,MainTreeNode(){return l!=null?null:E(Se,{features:Te.Hidden,ref:a})}}}let ze=Symbol("ForcePortalRootContext");function xt(){return O(ze,!1)}let $e=F({name:"ForcePortalRoot",props:{as:{type:[Object,String],default:"template"},force:{type:Boolean,default:!1}},setup(e,{slots:t,attrs:l}){return _(ze,e.force),()=>{let{force:a,...n}=e;return C({theirProps:n,ourProps:{},slot:{},slots:t,attrs:l,name:"ForcePortalRoot"})}}}),qe=Symbol("StackContext");var Le=(e=>(e[e.Add=0]="Add",e[e.Remove=1]="Remove",e))(Le||{});function Ot(){return O(qe,()=>{})}function At({type:e,enabled:t,element:l,onUpdate:a}){let n=Ot();function r(...o){a==null||a(...o),n(...o)}T(()=>{X(t,(o,i)=>{o?r(0,e,l):i===!0&&r(1,e,l)},{immediate:!0,flush:"sync"})}),x(()=>{t.value&&r(1,e,l)}),_(qe,r)}let Ge=Symbol("DescriptionContext");function Bt(){let e=O(Ge,null);if(e===null)throw new Error("Missing parent");return e}function Rt({slot:e=p({}),name:t="Description",props:l={}}={}){let a=p([]);function n(r){return a.value.push(r),()=>{let o=a.value.indexOf(r);o!==-1&&a.value.splice(o,1)}}return _(Ge,{register:n,slot:e,name:t,props:l}),f(()=>a.value.length>0?a.value.join(" "):void 0)}let ol=F({name:"Description",props:{as:{type:[Object,String],default:"p"},id:{type:String,default:()=>`headlessui-description-${z()}`}},setup(e,{attrs:t,slots:l}){let a=Bt();return T(()=>x(a.register(e.id))),()=>{let{name:n="Description",slot:r=p({}),props:o={}}=a,{id:i,...s}=e,d={...Object.entries(o).reduce((u,[g,c])=>Object.assign(u,{[g]:N(c)}),{}),id:i};return C({ourProps:d,theirProps:s,slot:r.value,attrs:t,slots:l,name:n})}}});function kt(e){let t=oe(e);if(!t){if(e===null)return null;throw new Error(`[Headless UI]: Cannot find ownerDocument for contextElement: ${e}`)}let l=t.getElementById("headlessui-portal-root");if(l)return l;let a=t.createElement("div");return a.setAttribute("id","headlessui-portal-root"),t.body.appendChild(a)}let Ke=F({name:"Portal",props:{as:{type:[Object,String],default:"div"}},setup(e,{slots:t,attrs:l}){let a=p(null),n=f(()=>oe(a)),r=xt(),o=O(Qe,null),i=p(r===!0||o==null?kt(a.value):o.resolveTarget()),s=p(!1);T(()=>{s.value=!0}),k(()=>{r||o!=null&&(i.value=o.resolveTarget())});let d=O(Fe,null),u=!1,g=ft();return X(a,()=>{if(u||!d)return;let c=b(a);c&&(x(d.register(c),g),u=!0)}),x(()=>{var c,h;let m=(c=n.value)==null?void 0:c.getElementById("headlessui-portal-root");m&&i.value===m&&i.value.children.length<=0&&((h=i.value.parentElement)==null||h.removeChild(i.value))}),()=>{if(!s.value||i.value===null)return null;let c={ref:a,"data-headlessui-portal":""};return E(dt,{to:i.value},C({ourProps:c,theirProps:e,slot:{},attrs:l,slots:t,name:"Portal"}))}}}),Fe=Symbol("PortalParentContext");function _t(){let e=O(Fe,null),t=p([]);function l(r){return t.value.push(r),e&&e.register(r),()=>a(r)}function a(r){let o=t.value.indexOf(r);o!==-1&&t.value.splice(o,1),e&&e.unregister(r)}let n={register:l,unregister:a,portals:t};return[t,F({name:"PortalWrapper",setup(r,{slots:o}){return _(Fe,n),()=>{var i;return(i=o.default)==null?void 0:i.call(o)}}})]}let Qe=Symbol("PortalGroupContext"),Ht=F({name:"PortalGroup",props:{as:{type:[Object,String],default:"template"},target:{type:Object,default:null}},setup(e,{attrs:t,slots:l}){let a=ct({resolveTarget(){return e.target}});return _(Qe,a),()=>{let{target:n,...r}=e;return C({theirProps:r,ourProps:{},slot:{},attrs:t,slots:l,name:"PortalGroup"})}}});var Mt=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(Mt||{});let Pe=Symbol("DialogContext");function ie(e){let t=O(Pe,null);if(t===null){let l=new Error(`<${e} /> is missing a parent <Dialog /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(l,ie),l}return t}let ue="DC8F892D-2EBD-447C-A4C8-A03058436FF4",Nt=F({name:"Dialog",inheritAttrs:!1,props:{as:{type:[Object,String],default:"div"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},open:{type:[Boolean,String],default:ue},initialFocus:{type:Object,default:null},id:{type:String,default:()=>`headlessui-dialog-${z()}`},role:{type:String,default:"dialog"}},emits:{close:e=>!0},setup(e,{emit:t,attrs:l,slots:a,expose:n}){var r;let o=p(!1);T(()=>{o.value=!0});let i=!1,s=f(()=>e.role==="dialog"||e.role==="alertdialog"?e.role:(i||(i=!0,console.warn(`Invalid role [${s}] passed to <Dialog />. Only \`dialog\` and and \`alertdialog\` are supported. Using \`dialog\` instead.`)),"dialog")),d=p(0),u=Me(),g=f(()=>e.open===ue&&u!==null?(u.value&P.Open)===P.Open:e.open),c=p(null),h=f(()=>oe(c));if(n({el:c,$el:c}),!(e.open!==ue||u!==null))throw new Error("You forgot to provide an `open` prop to the `Dialog`.");if(typeof g.value!="boolean")throw new Error(`You provided an \`open\` prop to the \`Dialog\`, but the value is not a boolean. Received: ${g.value===ue?void 0:e.open}`);let m=f(()=>o.value&&g.value?0:1),w=f(()=>m.value===0),S=f(()=>d.value>1),q=O(Pe,null)!==null,[A,G]=_t(),{resolveContainers:K,mainTreeNodeRef:ee,MainTreeNode:se}=Dt({portals:A,defaultContainers:[f(()=>{var v;return(v=$.panelRef.value)!=null?v:c.value})]}),ve=f(()=>S.value?"parent":"leaf"),te=f(()=>u!==null?(u.value&P.Closing)===P.Closing:!1),me=f(()=>q||te.value?!1:w.value),ge=f(()=>{var v,y,D;return(D=Array.from((y=(v=h.value)==null?void 0:v.querySelectorAll("body > *"))!=null?y:[]).find(L=>L.id==="headlessui-portal-root"?!1:L.contains(b(ee))&&L instanceof HTMLElement))!=null?D:null});_e(ge,me);let he=f(()=>S.value?!0:w.value),ye=f(()=>{var v,y,D;return(D=Array.from((y=(v=h.value)==null?void 0:v.querySelectorAll("[data-headlessui-portal]"))!=null?y:[]).find(L=>L.contains(b(ee))&&L instanceof HTMLElement))!=null?D:null});_e(ye,he),At({type:"Dialog",enabled:f(()=>m.value===0),element:c,onUpdate:(v,y)=>{if(y==="Dialog")return Y(v,{[Le.Add]:()=>d.value+=1,[Le.Remove]:()=>d.value-=1})}});let B=Rt({name:"DialogDescription",slot:f(()=>({open:g.value}))}),H=p(null),$={titleId:H,panelRef:p(null),dialogState:m,setTitleId(v){H.value!==v&&(H.value=v)},close(){t("close",!1)}};_(Pe,$);let Q=f(()=>!(!w.value||S.value));it(K,(v,y)=>{$.close(),pt(()=>y==null?void 0:y.focus())},Q);let Ae=f(()=>!(S.value||m.value!==0));We((r=h.value)==null?void 0:r.defaultView,"keydown",v=>{Ae.value&&(v.defaultPrevented||v.key===et.Escape&&(v.preventDefault(),v.stopPropagation(),$.close()))});let Be=f(()=>!(te.value||m.value!==0||q));return Ct(h,Be,v=>{var y;return{containers:[...(y=v.containers)!=null?y:[],K]}}),k(v=>{if(m.value!==0)return;let y=b(c);if(!y)return;let D=new ResizeObserver(L=>{for(let le of L){let M=le.target.getBoundingClientRect();M.x===0&&M.y===0&&M.width===0&&M.height===0&&$.close()}});D.observe(y),v(()=>D.disconnect())}),()=>{let{id:v,open:y,initialFocus:D,...L}=e,le={...l,ref:c,id:v,role:s.value,"aria-modal":m.value===0?!0:void 0,"aria-labelledby":H.value,"aria-describedby":B.value},M={open:m.value===0};return E($e,{force:!0},()=>[E(Ke,()=>E(Ht,{target:c.value},()=>E($e,{force:!1},()=>E(ae,{initialFocus:D,containers:K,features:w.value?Y(ve.value,{parent:ae.features.RestoreFocus,leaf:ae.features.All&~ae.features.FocusLock}):ae.features.None},()=>E(G,{},()=>C({ourProps:le,theirProps:{...L,...l},slot:M,attrs:l,slots:a,visible:m.value===0,features:Ee.RenderStrategy|Ee.Static,name:"Dialog"})))))),E(se)])}}});F({name:"DialogOverlay",props:{as:{type:[Object,String],default:"div"},id:{type:String,default:()=>`headlessui-dialog-overlay-${z()}`}},setup(e,{attrs:t,slots:l}){let a=ie("DialogOverlay");function n(r){r.target===r.currentTarget&&(r.preventDefault(),r.stopPropagation(),a.close())}return()=>{let{id:r,...o}=e;return C({ourProps:{id:r,"aria-hidden":!0,onClick:n},theirProps:o,slot:{open:a.dialogState.value===0},attrs:t,slots:l,name:"DialogOverlay"})}}});F({name:"DialogBackdrop",props:{as:{type:[Object,String],default:"div"},id:{type:String,default:()=>`headlessui-dialog-backdrop-${z()}`}},inheritAttrs:!1,setup(e,{attrs:t,slots:l,expose:a}){let n=ie("DialogBackdrop"),r=p(null);return a({el:r,$el:r}),T(()=>{if(n.panelRef.value===null)throw new Error("A <DialogBackdrop /> component is being used, but a <DialogPanel /> component is missing.")}),()=>{let{id:o,...i}=e,s={id:o,ref:r,"aria-hidden":!0};return E($e,{force:!0},()=>E(Ke,()=>C({ourProps:s,theirProps:{...t,...i},slot:{open:n.dialogState.value===0},attrs:t,slots:l,name:"DialogBackdrop"})))}}});let jt=F({name:"DialogPanel",props:{as:{type:[Object,String],default:"div"},id:{type:String,default:()=>`headlessui-dialog-panel-${z()}`}},setup(e,{attrs:t,slots:l,expose:a}){let n=ie("DialogPanel");a({el:n.panelRef,$el:n.panelRef});function r(o){o.stopPropagation()}return()=>{let{id:o,...i}=e,s={id:o,ref:n.panelRef,onClick:r};return C({ourProps:s,theirProps:i,slot:{open:n.dialogState.value===0},attrs:t,slots:l,name:"DialogPanel"})}}}),It=F({name:"DialogTitle",props:{as:{type:[Object,String],default:"h2"},id:{type:String,default:()=>`headlessui-dialog-title-${z()}`}},setup(e,{attrs:t,slots:l}){let a=ie("DialogTitle");return T(()=>{a.setTitleId(e.id),x(()=>a.setTitleId(null))}),()=>{let{id:n,...r}=e;return C({ourProps:{id:n},theirProps:r,slot:{open:a.dialogState.value===0},attrs:t,slots:l,name:"DialogTitle"})}}});function Wt(e){let t={called:!1};return(...l)=>{if(!t.called)return t.called=!0,e(...l)}}function we(e,...t){e&&t.length>0&&e.classList.add(...t)}function de(e,...t){e&&t.length>0&&e.classList.remove(...t)}var Ce=(e=>(e.Finished="finished",e.Cancelled="cancelled",e))(Ce||{});function Ut(e,t){let l=fe();if(!e)return l.dispose;let{transitionDuration:a,transitionDelay:n}=getComputedStyle(e),[r,o]=[a,n].map(i=>{let[s=0]=i.split(",").filter(Boolean).map(d=>d.includes("ms")?parseFloat(d):parseFloat(d)*1e3).sort((d,u)=>u-d);return s});return r!==0?l.setTimeout(()=>t("finished"),r+o):t("finished"),l.add(()=>t("cancelled")),l.dispose}function He(e,t,l,a,n,r){let o=fe(),i=r!==void 0?Wt(r):()=>{};return de(e,...n),we(e,...t,...l),o.nextFrame(()=>{de(e,...l),we(e,...a),o.add(Ut(e,s=>(de(e,...a,...t),we(e,...n),i(s))))}),o.add(()=>de(e,...t,...l,...a,...n)),o.add(()=>i("cancelled")),o.dispose}function j(e=""){return e.split(/\s+/).filter(t=>t.length>1)}let xe=Symbol("TransitionContext");var Vt=(e=>(e.Visible="visible",e.Hidden="hidden",e))(Vt||{});function Yt(){return O(xe,null)!==null}function zt(){let e=O(xe,null);if(e===null)throw new Error("A <TransitionChild /> is used but it is missing a parent <TransitionRoot />.");return e}function qt(){let e=O(Oe,null);if(e===null)throw new Error("A <TransitionChild /> is used but it is missing a parent <TransitionRoot />.");return e}let Oe=Symbol("NestingContext");function pe(e){return"children"in e?pe(e.children):e.value.filter(({state:t})=>t==="visible").length>0}function Ze(e){let t=p([]),l=p(!1);T(()=>l.value=!0),x(()=>l.value=!1);function a(r,o=W.Hidden){let i=t.value.findIndex(({id:s})=>s===r);i!==-1&&(Y(o,{[W.Unmount](){t.value.splice(i,1)},[W.Hidden](){t.value[i].state="hidden"}}),!pe(t)&&l.value&&(e==null||e()))}function n(r){let o=t.value.find(({id:i})=>i===r);return o?o.state!=="visible"&&(o.state="visible"):t.value.push({id:r,state:"visible"}),()=>a(r,W.Unmount)}return{children:t,register:n,unregister:a}}let Je=Ee.RenderStrategy,De=F({props:{as:{type:[Object,String],default:"div"},show:{type:[Boolean],default:null},unmount:{type:[Boolean],default:!0},appear:{type:[Boolean],default:!1},enter:{type:[String],default:""},enterFrom:{type:[String],default:""},enterTo:{type:[String],default:""},entered:{type:[String],default:""},leave:{type:[String],default:""},leaveFrom:{type:[String],default:""},leaveTo:{type:[String],default:""}},emits:{beforeEnter:()=>!0,afterEnter:()=>!0,beforeLeave:()=>!0,afterLeave:()=>!0},setup(e,{emit:t,attrs:l,slots:a,expose:n}){let r=p(0);function o(){r.value|=P.Opening,t("beforeEnter")}function i(){r.value&=~P.Opening,t("afterEnter")}function s(){r.value|=P.Closing,t("beforeLeave")}function d(){r.value&=~P.Closing,t("afterLeave")}if(!Yt()&&tt())return()=>E(Xe,{...e,onBeforeEnter:o,onAfterEnter:i,onBeforeLeave:s,onAfterLeave:d},a);let u=p(null),g=f(()=>e.unmount?W.Unmount:W.Hidden);n({el:u,$el:u});let{show:c,appear:h}=zt(),{register:m,unregister:w}=qt(),S=p(c.value?"visible":"hidden"),q={value:!0},A=z(),G={value:!1},K=Ze(()=>{!G.value&&S.value!=="hidden"&&(S.value="hidden",w(A),d())});T(()=>{let B=m(A);x(B)}),k(()=>{if(g.value===W.Hidden&&A){if(c.value&&S.value!=="visible"){S.value="visible";return}Y(S.value,{hidden:()=>w(A),visible:()=>m(A)})}});let ee=j(e.enter),se=j(e.enterFrom),ve=j(e.enterTo),te=j(e.entered),me=j(e.leave),ge=j(e.leaveFrom),he=j(e.leaveTo);T(()=>{k(()=>{if(S.value==="visible"){let B=b(u);if(B instanceof Comment&&B.data==="")throw new Error("Did you forget to passthrough the `ref` to the actual DOM node?")}})});function ye(B){let H=q.value&&!h.value,$=b(u);!$||!($ instanceof HTMLElement)||H||(G.value=!0,c.value&&o(),c.value||s(),B(c.value?He($,ee,se,ve,te,Q=>{G.value=!1,Q===Ce.Finished&&i()}):He($,me,ge,he,te,Q=>{G.value=!1,Q===Ce.Finished&&(pe(K)||(S.value="hidden",w(A),d()))})))}return T(()=>{X([c],(B,H,$)=>{ye($),q.value=!1},{immediate:!0})}),_(Oe,K),lt(f(()=>Y(S.value,{visible:P.Open,hidden:P.Closed})|r.value)),()=>{let{appear:B,show:H,enter:$,enterFrom:Q,enterTo:Ae,entered:Be,leave:v,leaveFrom:y,leaveTo:D,...L}=e,le={ref:u},M={...L,...h.value&&c.value&&Ne.isServer?{class:vt([l.class,L.class,...ee,...se])}:{}};return C({theirProps:M,ourProps:le,slot:{},slots:a,attrs:l,features:Je,visible:S.value==="visible",name:"TransitionChild"})}}}),Gt=De,Xe=F({inheritAttrs:!1,props:{as:{type:[Object,String],default:"div"},show:{type:[Boolean],default:null},unmount:{type:[Boolean],default:!0},appear:{type:[Boolean],default:!1},enter:{type:[String],default:""},enterFrom:{type:[String],default:""},enterTo:{type:[String],default:""},entered:{type:[String],default:""},leave:{type:[String],default:""},leaveFrom:{type:[String],default:""},leaveTo:{type:[String],default:""}},emits:{beforeEnter:()=>!0,afterEnter:()=>!0,beforeLeave:()=>!0,afterLeave:()=>!0},setup(e,{emit:t,attrs:l,slots:a}){let n=Me(),r=f(()=>e.show===null&&n!==null?(n.value&P.Open)===P.Open:e.show);k(()=>{if(![!0,!1].includes(r.value))throw new Error('A <Transition /> is used but it is missing a `:show="true | false"` prop.')});let o=p(r.value?"visible":"hidden"),i=Ze(()=>{o.value="hidden"}),s=p(!0),d={show:r,appear:f(()=>e.appear||!s.value)};return T(()=>{k(()=>{s.value=!1,r.value?o.value="visible":pe(i)||(o.value="hidden")})}),_(Oe,i),_(xe,d),()=>{let u=at(e,["show","appear","unmount","onBeforeEnter","onBeforeLeave","onAfterEnter","onAfterLeave"]),g={unmount:e.unmount};return C({ourProps:{...g,as:"template"},theirProps:{},slot:{},slots:{...a,default:()=>[E(Gt,{onBeforeEnter:()=>t("beforeEnter"),onAfterEnter:()=>t("afterEnter"),onBeforeLeave:()=>t("beforeLeave"),onAfterLeave:()=>t("afterLeave"),...l,...g,...u},a.default)]},attrs:{},features:Je,visible:o.value==="visible",name:"Transition"})}}});function Kt(e,t){return Ie(),mt("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true","data-slot":"icon"},[R("path",{d:"M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"})])}const Qt=R("div",{class:"fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"},null,-1),Zt={class:"fixed z-10 inset-0 overflow-y-auto"},Jt={class:"flex items-center justify-center min-h-full p-4 text-center sm:p-0"},Xt={class:"mt-3 text-center sm:mt-0 sm:ml-2 sm:mr-2 sm:text-left"},el={class:"border-b border-gray-200 bg-white px-2 py-1 md:py-3"},tl=R("span",{class:"sr-only"},"Close",-1),ll={class:"mt-5 text-lg text-gray-700 p-1"},il={__name:"Modal",props:{open:Boolean},emits:["modalClose"],setup(e,{emit:t}){const l=t;function a(){l("modalClose")}return(n,r)=>(Ie(),gt(N(Xe),{as:"template",show:e.open},{default:Z(()=>[J(N(Nt),{as:"div",class:"relative z-10",onClose:a},{default:Z(()=>[J(N(De),{as:"template",enter:"ease-out duration-300","enter-from":"opacity-0","enter-to":"opacity-100",leave:"ease-in duration-200","leave-from":"opacity-100","leave-to":"opacity-0"},{default:Z(()=>[Qt]),_:1}),R("div",Zt,[R("div",Jt,[J(N(De),{as:"template",enter:"ease-out duration-300","enter-from":"opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95","enter-to":"opacity-100 translate-y-0 sm:scale-100",leave:"ease-in duration-200","leave-from":"opacity-100 translate-y-0 sm:scale-100","leave-to":"opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"},{default:Z(()=>[J(N(jt),{class:"relative bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all my-2 md:my-8 max-w-3xl max-h-3xl w-full p-2 md:p-6 flex flex-col"},{default:Z(()=>[R("div",Xt,[R("div",el,[J(N(It),{as:"h3",class:"text-xl leading-6 font-medium text-gray-900 flex justify-between"},{default:Z(()=>[ke(n.$slots,"header"),R("button",{type:"button",class:"bg-white rounded-md text-gray-400 hover:text-gray-500",onClick:a},[tl,J(N(Kt),{class:"h-6 w-6","aria-hidden":"true"})])]),_:3})]),R("div",ll,[ke(n.$slots,"default")])])]),_:3})]),_:3})])])]),_:3})]),_:3},8,["show"]))}};export{il as _};
