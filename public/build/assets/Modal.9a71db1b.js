import{o as C,u as O,V as S,t as H,p as Be,l as V,R as ve,a as Ve,f as Qe,O as M,c as Ye,w as Je}from"./open-closed.d2c7fa55.js";import{x as T,i as f,k as m,y as P,z as x,F as Ke,j as F,A as re,B as N,C as R,D as B,T as Ze,E as Xe,b as k,G as et,o as ke,g as tt,d as A,c as nt,w as W,a as z,r as De}from"./app.02368991.js";const ae=typeof window>"u"||typeof document>"u";function Q(e){if(ae)return null;if(e instanceof Node)return e.ownerDocument;if(e!=null&&e.hasOwnProperty("value")){let t=C(e);if(t)return t.ownerDocument}return document}let me=["[contentEditable=true]","[tabindex]","a[href]","area[href]","button:not([disabled])","iframe","input:not([disabled])","select:not([disabled])","textarea:not([disabled])"].map(e=>`${e}:not([tabindex='-1'])`).join(",");var Z=(e=>(e[e.First=1]="First",e[e.Previous=2]="Previous",e[e.Next=4]="Next",e[e.Last=8]="Last",e[e.WrapAround=16]="WrapAround",e[e.NoScroll=32]="NoScroll",e))(Z||{}),_e=(e=>(e[e.Error=0]="Error",e[e.Overflow=1]="Overflow",e[e.Success=2]="Success",e[e.Underflow=3]="Underflow",e))(_e||{}),lt=(e=>(e[e.Previous=-1]="Previous",e[e.Next=1]="Next",e))(lt||{});function rt(e=document.body){return e==null?[]:Array.from(e.querySelectorAll(me))}var Re=(e=>(e[e.Strict=0]="Strict",e[e.Loose=1]="Loose",e))(Re||{});function at(e,t=0){var r;return e===((r=Q(e))==null?void 0:r.body)?!1:O(t,{[0](){return e.matches(me)},[1](){let n=e;for(;n!==null;){if(n.matches(me))return!0;n=n.parentElement}return!1}})}function K(e){e==null||e.focus({preventScroll:!0})}let ot=["textarea","input"].join(",");function it(e){var t,r;return(r=(t=e==null?void 0:e.matches)==null?void 0:t.call(e,ot))!=null?r:!1}function ut(e,t=r=>r){return e.slice().sort((r,n)=>{let a=t(r),l=t(n);if(a===null||l===null)return 0;let o=a.compareDocumentPosition(l);return o&Node.DOCUMENT_POSITION_FOLLOWING?-1:o&Node.DOCUMENT_POSITION_PRECEDING?1:0})}function he(e,t,r=!0,n=null){var a;let l=(a=Array.isArray(e)?e.length>0?e[0].ownerDocument:document:e==null?void 0:e.ownerDocument)!=null?a:document,o=Array.isArray(e)?r?ut(e):e:rt(e);n=n!=null?n:l.activeElement;let i=(()=>{if(t&5)return 1;if(t&10)return-1;throw new Error("Missing Focus.First, Focus.Previous, Focus.Next or Focus.Last")})(),s=(()=>{if(t&1)return 0;if(t&2)return Math.max(0,o.indexOf(n))-1;if(t&4)return Math.max(0,o.indexOf(n))+1;if(t&8)return o.length-1;throw new Error("Missing Focus.First, Focus.Previous, Focus.Next or Focus.Last")})(),u=t&32?{preventScroll:!0}:{},d=0,c=o.length,v;do{if(d>=c||d+c<=0)return 0;let b=s+d;if(t&16)b=(b+c)%c;else{if(b<0)return 3;if(b>=c)return 1}v=o[b],v==null||v.focus(u),d+=i}while(v!==l.activeElement);return t&6&&it(v)&&v.select(),v.hasAttribute("tabindex")||v.setAttribute("tabindex","0"),2}function fe(e,t,r){ae||T(n=>{document.addEventListener(e,t,r),n(()=>document.removeEventListener(e,t,r))})}function st(e,t,r=m(()=>!0)){function n(l,o){if(!r.value||l.defaultPrevented)return;let i=o(l);if(i===null||!i.ownerDocument.documentElement.contains(i))return;let s=function u(d){return typeof d=="function"?u(d()):Array.isArray(d)||d instanceof Set?d:[d]}(e);for(let u of s){if(u===null)continue;let d=u instanceof HTMLElement?u:C(u);if(d!=null&&d.contains(i))return}return!at(i,Re.Loose)&&i.tabIndex!==-1&&l.preventDefault(),t(l,i)}let a=f(null);fe("mousedown",l=>{r.value&&(a.value=l.target)},!0),fe("click",l=>{!a.value||(n(l,()=>a.value),a.value=null)},!0),fe("blur",l=>n(l,()=>window.document.activeElement instanceof HTMLIFrameElement?window.document.activeElement:null),!0)}var le=(e=>(e[e.None=1]="None",e[e.Focusable=2]="Focusable",e[e.Hidden=4]="Hidden",e))(le||{});let ge=P({name:"Hidden",props:{as:{type:[Object,String],default:"div"},features:{type:Number,default:1}},setup(e,{slots:t,attrs:r}){return()=>{let{features:n,...a}=e,l={"aria-hidden":(n&2)===2?!0:void 0,style:{position:"fixed",top:1,left:1,width:1,height:0,padding:0,margin:-1,overflow:"hidden",clip:"rect(0, 0, 0, 0)",whiteSpace:"nowrap",borderWidth:"0",...(n&4)===4&&(n&2)!==2&&{display:"none"}}};return S({ourProps:l,theirProps:a,slot:{},attrs:r,slots:t,name:"Hidden"})}}});function dt(e,t,r){ae||T(n=>{window.addEventListener(e,t,r),n(()=>window.removeEventListener(e,t,r))})}var ye=(e=>(e[e.Forwards=0]="Forwards",e[e.Backwards=1]="Backwards",e))(ye||{});function ct(){let e=f(0);return dt("keydown",t=>{t.key==="Tab"&&(e.value=t.shiftKey?1:0)}),e}function Ne(e,t,r,n){ae||T(a=>{e=e!=null?e:window,e.addEventListener(t,r,n),a(()=>e.removeEventListener(t,r,n))})}function ft(e){typeof queueMicrotask=="function"?queueMicrotask(e):Promise.resolve().then(e).catch(t=>setTimeout(()=>{throw t}))}var je=(e=>(e[e.None=1]="None",e[e.InitialFocus=2]="InitialFocus",e[e.TabLock=4]="TabLock",e[e.FocusLock=8]="FocusLock",e[e.RestoreFocus=16]="RestoreFocus",e[e.All=30]="All",e))(je||{});let J=Object.assign(P({name:"FocusTrap",props:{as:{type:[Object,String],default:"div"},initialFocus:{type:Object,default:null},features:{type:Number,default:30},containers:{type:Object,default:f(new Set)}},inheritAttrs:!1,setup(e,{attrs:t,slots:r,expose:n}){let a=f(null);n({el:a,$el:a});let l=m(()=>Q(a));pt({ownerDocument:l},m(()=>Boolean(e.features&16)));let o=vt({ownerDocument:l,container:a,initialFocus:m(()=>e.initialFocus)},m(()=>Boolean(e.features&2)));mt({ownerDocument:l,container:a,containers:e.containers,previousActiveElement:o},m(()=>Boolean(e.features&8)));let i=ct();function s(){let u=C(a);!u||O(i.value,{[ye.Forwards]:()=>he(u,Z.First),[ye.Backwards]:()=>he(u,Z.Last)})}return()=>{let u={},d={ref:a},{features:c,initialFocus:v,containers:b,...w}=e;return x(Ke,[Boolean(c&4)&&x(ge,{as:"button",type:"button",onFocus:s,features:le.Focusable}),S({ourProps:d,theirProps:{...t,...w},slot:u,attrs:t,slots:r,name:"FocusTrap"}),Boolean(c&4)&&x(ge,{as:"button",type:"button",onFocus:s,features:le.Focusable})])}}}),{features:je});function pt({ownerDocument:e},t){let r=f(null);function n(){var l;r.value||(r.value=(l=e.value)==null?void 0:l.activeElement)}function a(){!r.value||(K(r.value),r.value=null)}F(()=>{re(t,(l,o)=>{l!==o&&(l?n():a())},{immediate:!0})}),N(a)}function vt({ownerDocument:e,container:t,initialFocus:r},n){let a=f(null),l=f(!1);return F(()=>l.value=!0),N(()=>l.value=!1),F(()=>{re([t,r,n],(o,i)=>{if(o.every((u,d)=>(i==null?void 0:i[d])===u)||!n.value)return;let s=C(t);!s||ft(()=>{var u,d;if(!l.value)return;let c=C(r),v=(u=e.value)==null?void 0:u.activeElement;if(c){if(c===v){a.value=v;return}}else if(s.contains(v)){a.value=v;return}c?K(c):he(s,Z.First|Z.NoScroll)===_e.Error&&console.warn("There are no focusable elements inside the <FocusTrap />"),a.value=(d=e.value)==null?void 0:d.activeElement})},{immediate:!0,flush:"post"})}),a}function mt({ownerDocument:e,container:t,containers:r,previousActiveElement:n},a){var l;Ne((l=e.value)==null?void 0:l.defaultView,"focus",o=>{if(!a.value)return;let i=new Set(r==null?void 0:r.value);i.add(t);let s=n.value;if(!s)return;let u=o.target;u&&u instanceof HTMLElement?ht(i,u)?(n.value=u,K(u)):(o.preventDefault(),o.stopPropagation(),K(s)):K(n.value)},!0)}function ht(e,t){var r;for(let n of e)if((r=n.value)!=null&&r.contains(t))return!0;return!1}let Le="body > *",G=new Set,_=new Map;function Ae(e){e.setAttribute("aria-hidden","true"),e.inert=!0}function Oe(e){let t=_.get(e);!t||(t["aria-hidden"]===null?e.removeAttribute("aria-hidden"):e.setAttribute("aria-hidden",t["aria-hidden"]),e.inert=t.inert)}function gt(e,t=f(!0)){T(r=>{if(!t.value||!e.value)return;let n=e.value,a=Q(n);if(a){G.add(n);for(let l of _.keys())l.contains(n)&&(Oe(l),_.delete(l));a.querySelectorAll(Le).forEach(l=>{if(l instanceof HTMLElement){for(let o of G)if(l.contains(o))return;G.size===1&&(_.set(l,{"aria-hidden":l.getAttribute("aria-hidden"),inert:l.inert}),Ae(l))}}),r(()=>{if(G.delete(n),G.size>0)a.querySelectorAll(Le).forEach(l=>{if(l instanceof HTMLElement&&!_.has(l)){for(let o of G)if(l.contains(o))return;_.set(l,{"aria-hidden":l.getAttribute("aria-hidden"),inert:l.inert}),Ae(l)}});else for(let l of _.keys())Oe(l),_.delete(l)})}})}let Me=Symbol("ForcePortalRootContext");function yt(){return B(Me,!1)}let be=P({name:"ForcePortalRoot",props:{as:{type:[Object,String],default:"template"},force:{type:Boolean,default:!1}},setup(e,{slots:t,attrs:r}){return R(Me,e.force),()=>{let{force:n,...a}=e;return S({theirProps:a,ourProps:{},slot:{},slots:t,attrs:r,name:"ForcePortalRoot"})}}});function bt(e){let t=Q(e);if(!t){if(e===null)return null;throw new Error(`[Headless UI]: Cannot find ownerDocument for contextElement: ${e}`)}let r=t.getElementById("headlessui-portal-root");if(r)return r;let n=t.createElement("div");return n.setAttribute("id","headlessui-portal-root"),t.body.appendChild(n)}let He=P({name:"Portal",props:{as:{type:[Object,String],default:"div"}},setup(e,{slots:t,attrs:r}){let n=f(null),a=m(()=>Q(n)),l=yt(),o=B(Ie,null),i=f(l===!0||o==null?bt(n.value):o.resolveTarget());return T(()=>{l||o!=null&&(i.value=o.resolveTarget())}),N(()=>{var s,u;let d=(s=a.value)==null?void 0:s.getElementById("headlessui-portal-root");!d||i.value===d&&i.value.children.length<=0&&((u=i.value.parentElement)==null||u.removeChild(i.value))}),()=>{if(i.value===null)return null;let s={ref:n,"data-headlessui-portal":""};return x(Ze,{to:i.value},S({ourProps:s,theirProps:e,slot:{},attrs:r,slots:t,name:"Portal"}))}}}),Ie=Symbol("PortalGroupContext"),wt=P({name:"PortalGroup",props:{as:{type:[Object,String],default:"template"},target:{type:Object,default:null}},setup(e,{attrs:t,slots:r}){let n=Xe({resolveTarget(){return e.target}});return R(Ie,n),()=>{let{target:a,...l}=e;return S({theirProps:l,ourProps:{},slot:{},attrs:t,slots:r,name:"PortalGroup"})}}}),Ue=Symbol("StackContext");var we=(e=>(e[e.Add=0]="Add",e[e.Remove=1]="Remove",e))(we||{});function Et(){return B(Ue,()=>{})}function xt({type:e,enabled:t,element:r,onUpdate:n}){let a=Et();function l(...o){n==null||n(...o),a(...o)}F(()=>{re(t,(o,i)=>{o?l(0,e,r):i===!0&&l(1,e,r)},{immediate:!0,flush:"sync"})}),N(()=>{t.value&&l(1,e,r)}),R(Ue,l)}let qe=Symbol("DescriptionContext");function Ft(){let e=B(qe,null);if(e===null)throw new Error("Missing parent");return e}function St({slot:e=f({}),name:t="Description",props:r={}}={}){let n=f([]);function a(l){return n.value.push(l),()=>{let o=n.value.indexOf(l);o!==-1&&n.value.splice(o,1)}}return R(qe,{register:a,slot:e,name:t,props:r}),m(()=>n.value.length>0?n.value.join(" "):void 0)}let Vt=P({name:"Description",props:{as:{type:[Object,String],default:"p"}},setup(e,{attrs:t,slots:r}){let n=Ft(),a=`headlessui-description-${H()}`;return F(()=>N(n.register(a))),()=>{let{name:l="Description",slot:o=f({}),props:i={}}=n,s=e,u={...Object.entries(i).reduce((d,[c,v])=>Object.assign(d,{[c]:k(v)}),{}),id:a};return S({ourProps:u,theirProps:s,slot:o.value,attrs:t,slots:r,name:l})}}});function Se(){let e=[],t=[],r={enqueue(n){t.push(n)},addEventListener(n,a,l,o){return n.addEventListener(a,l,o),r.add(()=>n.removeEventListener(a,l,o))},requestAnimationFrame(...n){let a=requestAnimationFrame(...n);r.add(()=>cancelAnimationFrame(a))},nextFrame(...n){r.requestAnimationFrame(()=>{r.requestAnimationFrame(...n)})},setTimeout(...n){let a=setTimeout(...n);r.add(()=>clearTimeout(a))},add(n){e.push(n)},dispose(){for(let n of e.splice(0))n()},async workQueue(){for(let n of t.splice(0))await n()}};return r}function Pt(){return/iPhone/gi.test(window.navigator.platform)||/Mac/gi.test(window.navigator.platform)&&window.navigator.maxTouchPoints>0}var Tt=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(Tt||{});let Ee=Symbol("DialogContext");function X(e){let t=B(Ee,null);if(t===null){let r=new Error(`<${e} /> is missing a parent <Dialog /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(r,X),r}return t}let te="DC8F892D-2EBD-447C-A4C8-A03058436FF4",$t=P({name:"Dialog",inheritAttrs:!1,props:{as:{type:[Object,String],default:"div"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},open:{type:[Boolean,String],default:te},initialFocus:{type:Object,default:null}},emits:{close:e=>!0},setup(e,{emit:t,attrs:r,slots:n,expose:a}){var l;let o=f(!1);F(()=>{o.value=!0});let i=f(0),s=Be(),u=m(()=>e.open===te&&s!==null?O(s.value,{[V.Open]:!0,[V.Closed]:!1}):e.open),d=f(new Set),c=f(null),v=f(null),b=m(()=>Q(c));if(a({el:c,$el:c}),!(e.open!==te||s!==null))throw new Error("You forgot to provide an `open` prop to the `Dialog`.");if(typeof u.value!="boolean")throw new Error(`You provided an \`open\` prop to the \`Dialog\`, but the value is not a boolean. Received: ${u.value===te?void 0:e.open}`);let w=m(()=>o.value&&u.value?0:1),Y=m(()=>w.value===0),I=m(()=>i.value>1),ie=B(Ee,null)!==null,ue=m(()=>I.value?"parent":"leaf");gt(c,m(()=>I.value?Y.value:!1)),xt({type:"Dialog",enabled:m(()=>w.value===0),element:c,onUpdate:(h,p,g)=>{if(p==="Dialog")return O(h,{[we.Add](){d.value.add(g),i.value+=1},[we.Remove](){d.value.delete(g),i.value-=1}})}});let ee=St({name:"DialogDescription",slot:m(()=>({open:u.value}))}),se=`headlessui-dialog-${H()}`,U=f(null),$={titleId:U,panelRef:f(null),dialogState:w,setTitleId(h){U.value!==h&&(U.value=h)},close(){t("close",!1)}};return R(Ee,$),st(()=>{var h,p,g;return[...Array.from((p=(h=b.value)==null?void 0:h.querySelectorAll("body > *, [data-headlessui-portal]"))!=null?p:[]).filter(y=>!(!(y instanceof HTMLElement)||y.contains(C(v))||$.panelRef.value&&y.contains($.panelRef.value))),(g=$.panelRef.value)!=null?g:c.value]},(h,p)=>{$.close(),et(()=>p==null?void 0:p.focus())},m(()=>w.value===0&&!I.value)),Ne((l=b.value)==null?void 0:l.defaultView,"keydown",h=>{h.defaultPrevented||h.key===Ve.Escape&&w.value===0&&(I.value||(h.preventDefault(),h.stopPropagation(),$.close()))}),T(h=>{var p;if(w.value!==0||ie)return;let g=b.value;if(!g)return;let y=Se();function E(L,q,$e){let ce=L.style.getPropertyValue(q);return Object.assign(L.style,{[q]:$e}),y.add(()=>{Object.assign(L.style,{[q]:ce})})}let D=g==null?void 0:g.documentElement,de=((p=g.defaultView)!=null?p:window).innerWidth-D.clientWidth;if(E(D,"overflow","hidden"),de>0){let L=D.clientWidth-D.offsetWidth,q=de-L;E(D,"paddingRight",`${q}px`)}if(Pt()){let L=window.pageYOffset;E(D,"position","fixed"),E(D,"marginTop",`-${L}px`),E(D,"width","100%"),y.add(()=>window.scrollTo(0,L))}h(y.dispose)}),T(h=>{if(w.value!==0)return;let p=C(c);if(!p)return;let g=new IntersectionObserver(y=>{for(let E of y)E.boundingClientRect.x===0&&E.boundingClientRect.y===0&&E.boundingClientRect.width===0&&E.boundingClientRect.height===0&&$.close()});g.observe(p),h(()=>g.disconnect())}),()=>{let h={...r,ref:c,id:se,role:"dialog","aria-modal":w.value===0?!0:void 0,"aria-labelledby":U.value,"aria-describedby":ee.value},{open:p,initialFocus:g,...y}=e,E={open:w.value===0};return x(be,{force:!0},()=>[x(He,()=>x(wt,{target:c.value},()=>x(be,{force:!1},()=>x(J,{initialFocus:g,containers:d,features:Y.value?O(ue.value,{parent:J.features.RestoreFocus,leaf:J.features.All&~J.features.FocusLock}):J.features.None},()=>S({ourProps:h,theirProps:y,slot:E,attrs:r,slots:n,visible:w.value===0,features:ve.RenderStrategy|ve.Static,name:"Dialog"}))))),x(ge,{features:le.Hidden,ref:v})])}}});P({name:"DialogOverlay",props:{as:{type:[Object,String],default:"div"}},setup(e,{attrs:t,slots:r}){let n=X("DialogOverlay"),a=`headlessui-dialog-overlay-${H()}`;function l(o){o.target===o.currentTarget&&(o.preventDefault(),o.stopPropagation(),n.close())}return()=>S({ourProps:{id:a,"aria-hidden":!0,onClick:l},theirProps:e,slot:{open:n.dialogState.value===0},attrs:t,slots:r,name:"DialogOverlay"})}});P({name:"DialogBackdrop",props:{as:{type:[Object,String],default:"div"}},inheritAttrs:!1,setup(e,{attrs:t,slots:r,expose:n}){let a=X("DialogBackdrop"),l=`headlessui-dialog-backdrop-${H()}`,o=f(null);return n({el:o,$el:o}),F(()=>{if(a.panelRef.value===null)throw new Error("A <DialogBackdrop /> component is being used, but a <DialogPanel /> component is missing.")}),()=>{let i=e,s={id:l,ref:o,"aria-hidden":!0};return x(be,{force:!0},()=>x(He,()=>S({ourProps:s,theirProps:{...t,...i},slot:{open:a.dialogState.value===0},attrs:t,slots:r,name:"DialogBackdrop"})))}}});let Dt=P({name:"DialogPanel",props:{as:{type:[Object,String],default:"div"}},setup(e,{attrs:t,slots:r,expose:n}){let a=X("DialogPanel"),l=`headlessui-dialog-panel-${H()}`;n({el:a.panelRef,$el:a.panelRef});function o(i){i.stopPropagation()}return()=>{let i={id:l,ref:a.panelRef,onClick:o};return S({ourProps:i,theirProps:e,slot:{open:a.dialogState.value===0},attrs:t,slots:r,name:"DialogPanel"})}}}),Lt=P({name:"DialogTitle",props:{as:{type:[Object,String],default:"h2"}},setup(e,{attrs:t,slots:r}){let n=X("DialogTitle"),a=`headlessui-dialog-title-${H()}`;return F(()=>{n.setTitleId(a),N(()=>n.setTitleId(null))}),()=>S({ourProps:{id:a},theirProps:e,slot:{open:n.dialogState.value===0},attrs:t,slots:r,name:"DialogTitle"})}});function At(e){let t={called:!1};return(...r)=>{if(!t.called)return t.called=!0,e(...r)}}function pe(e,...t){e&&t.length>0&&e.classList.add(...t)}function ne(e,...t){e&&t.length>0&&e.classList.remove(...t)}var xe=(e=>(e.Finished="finished",e.Cancelled="cancelled",e))(xe||{});function Ot(e,t){let r=Se();if(!e)return r.dispose;let{transitionDuration:n,transitionDelay:a}=getComputedStyle(e),[l,o]=[n,a].map(i=>{let[s=0]=i.split(",").filter(Boolean).map(u=>u.includes("ms")?parseFloat(u):parseFloat(u)*1e3).sort((u,d)=>d-u);return s});return l!==0?r.setTimeout(()=>t("finished"),l+o):t("finished"),r.add(()=>t("cancelled")),r.dispose}function Ce(e,t,r,n,a,l){let o=Se(),i=l!==void 0?At(l):()=>{};return ne(e,...a),pe(e,...t,...r),o.nextFrame(()=>{ne(e,...r),pe(e,...n),o.add(Ot(e,s=>(ne(e,...n,...t),pe(e,...a),i(s))))}),o.add(()=>ne(e,...t,...r,...n,...a)),o.add(()=>i("cancelled")),o.dispose}function j(e=""){return e.split(" ").filter(t=>t.trim().length>1)}let Pe=Symbol("TransitionContext");var Ct=(e=>(e.Visible="visible",e.Hidden="hidden",e))(Ct||{});function Bt(){return B(Pe,null)!==null}function kt(){let e=B(Pe,null);if(e===null)throw new Error("A <TransitionChild /> is used but it is missing a parent <TransitionRoot />.");return e}function _t(){let e=B(Te,null);if(e===null)throw new Error("A <TransitionChild /> is used but it is missing a parent <TransitionRoot />.");return e}let Te=Symbol("NestingContext");function oe(e){return"children"in e?oe(e.children):e.value.filter(({state:t})=>t==="visible").length>0}function We(e){let t=f([]),r=f(!1);F(()=>r.value=!0),N(()=>r.value=!1);function n(l,o=M.Hidden){let i=t.value.findIndex(({id:s})=>s===l);i!==-1&&(O(o,{[M.Unmount](){t.value.splice(i,1)},[M.Hidden](){t.value[i].state="hidden"}}),!oe(t)&&r.value&&(e==null||e()))}function a(l){let o=t.value.find(({id:i})=>i===l);return o?o.state!=="visible"&&(o.state="visible"):t.value.push({id:l,state:"visible"}),()=>n(l,M.Unmount)}return{children:t,register:a,unregister:n}}let ze=ve.RenderStrategy,Fe=P({props:{as:{type:[Object,String],default:"div"},show:{type:[Boolean],default:null},unmount:{type:[Boolean],default:!0},appear:{type:[Boolean],default:!1},enter:{type:[String],default:""},enterFrom:{type:[String],default:""},enterTo:{type:[String],default:""},entered:{type:[String],default:""},leave:{type:[String],default:""},leaveFrom:{type:[String],default:""},leaveTo:{type:[String],default:""}},emits:{beforeEnter:()=>!0,afterEnter:()=>!0,beforeLeave:()=>!0,afterLeave:()=>!0},setup(e,{emit:t,attrs:r,slots:n,expose:a}){if(!Bt()&&Qe())return()=>x(Ge,{...e,onBeforeEnter:()=>t("beforeEnter"),onAfterEnter:()=>t("afterEnter"),onBeforeLeave:()=>t("beforeLeave"),onAfterLeave:()=>t("afterLeave")},n);let l=f(null),o=f("visible"),i=m(()=>e.unmount?M.Unmount:M.Hidden);a({el:l,$el:l});let{show:s,appear:u}=kt(),{register:d,unregister:c}=_t(),v={value:!0},b=H(),w={value:!1},Y=We(()=>{w.value||(o.value="hidden",c(b),t("afterLeave"))});F(()=>{let p=d(b);N(p)}),T(()=>{if(i.value===M.Hidden&&!!b){if(s&&o.value!=="visible"){o.value="visible";return}O(o.value,{hidden:()=>c(b),visible:()=>d(b)})}});let I=j(e.enter),ie=j(e.enterFrom),ue=j(e.enterTo),ee=j(e.entered),se=j(e.leave),U=j(e.leaveFrom),$=j(e.leaveTo);F(()=>{T(()=>{if(o.value==="visible"){let p=C(l);if(p instanceof Comment&&p.data==="")throw new Error("Did you forget to passthrough the `ref` to the actual DOM node?")}})});function h(p){let g=v.value&&!u.value,y=C(l);!y||!(y instanceof HTMLElement)||g||(w.value=!0,s.value&&t("beforeEnter"),s.value||t("beforeLeave"),p(s.value?Ce(y,I,ie,ue,ee,E=>{w.value=!1,E===xe.Finished&&t("afterEnter")}):Ce(y,se,U,$,ee,E=>{w.value=!1,E===xe.Finished&&(oe(Y)||(o.value="hidden",c(b),t("afterLeave")))})))}return F(()=>{re([s],(p,g,y)=>{h(y),v.value=!1},{immediate:!0})}),R(Te,Y),Ye(m(()=>O(o.value,{visible:V.Open,hidden:V.Closed}))),()=>{let{appear:p,show:g,enter:y,enterFrom:E,enterTo:D,entered:de,leave:L,leaveFrom:q,leaveTo:$e,...ce}=e;return S({theirProps:ce,ourProps:{ref:l},slot:{},slots:n,attrs:r,features:ze,visible:o.value==="visible",name:"TransitionChild"})}}}),Rt=Fe,Ge=P({inheritAttrs:!1,props:{as:{type:[Object,String],default:"div"},show:{type:[Boolean],default:null},unmount:{type:[Boolean],default:!0},appear:{type:[Boolean],default:!1},enter:{type:[String],default:""},enterFrom:{type:[String],default:""},enterTo:{type:[String],default:""},entered:{type:[String],default:""},leave:{type:[String],default:""},leaveFrom:{type:[String],default:""},leaveTo:{type:[String],default:""}},emits:{beforeEnter:()=>!0,afterEnter:()=>!0,beforeLeave:()=>!0,afterLeave:()=>!0},setup(e,{emit:t,attrs:r,slots:n}){let a=Be(),l=m(()=>e.show===null&&a!==null?O(a.value,{[V.Open]:!0,[V.Closed]:!1}):e.show);T(()=>{if(![!0,!1].includes(l.value))throw new Error('A <Transition /> is used but it is missing a `:show="true | false"` prop.')});let o=f(l.value?"visible":"hidden"),i=We(()=>{o.value="hidden"}),s=f(!0),u={show:l,appear:m(()=>e.appear||!s.value)};return F(()=>{T(()=>{s.value=!1,l.value?o.value="visible":oe(i)||(o.value="hidden")})}),R(Te,i),R(Pe,u),()=>{let d=Je(e,["show","appear","unmount","onBeforeEnter","onBeforeLeave","onAfterEnter","onAfterLeave"]),c={unmount:e.unmount};return S({ourProps:{...c,as:"template"},theirProps:{},slot:{},slots:{...n,default:()=>[x(Rt,{onBeforeEnter:()=>t("beforeEnter"),onAfterEnter:()=>t("afterEnter"),onBeforeLeave:()=>t("beforeLeave"),onAfterLeave:()=>t("afterLeave"),...r,...c,...d},n.default)]},attrs:{},features:ze,visible:o.value==="visible",name:"Transition"})}}});function Nt(e,t){return ke(),tt("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[A("path",{d:"M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"})])}const jt=A("div",{class:"fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"},null,-1),Mt={class:"fixed z-10 inset-0 overflow-y-auto"},Ht={class:"flex items-center justify-center min-h-full p-4 text-center sm:p-0"},It={class:"mt-3 text-center sm:mt-0 sm:ml-2 sm:mr-2 sm:text-left"},Ut={class:"border-b border-gray-200 bg-white px-2 py-1 md:py-3"},qt=A("span",{class:"sr-only"},"Close",-1),Wt={class:"mt-5 text-lg text-gray-700 p-1"},Qt={__name:"Modal",props:{open:Boolean},emits:["modalClose"],setup(e,{emit:t}){function r(){t("modalClose")}return(n,a)=>(ke(),nt(k(Ge),{as:"template",show:e.open},{default:W(()=>[z(k($t),{as:"div",class:"relative z-10",onClose:r},{default:W(()=>[z(k(Fe),{as:"template",enter:"ease-out duration-300","enter-from":"opacity-0","enter-to":"opacity-100",leave:"ease-in duration-200","leave-from":"opacity-100","leave-to":"opacity-0"},{default:W(()=>[jt]),_:1}),A("div",Mt,[A("div",Ht,[z(k(Fe),{as:"template",enter:"ease-out duration-300","enter-from":"opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95","enter-to":"opacity-100 translate-y-0 sm:scale-100",leave:"ease-in duration-200","leave-from":"opacity-100 translate-y-0 sm:scale-100","leave-to":"opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"},{default:W(()=>[z(k(Dt),{class:"relative bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all my-2 md:my-8 max-w-3xl max-h-3xl w-full p-2 md:p-6 flex flex-col"},{default:W(()=>[A("div",It,[A("div",Ut,[z(k(Lt),{as:"h3",class:"text-xl leading-6 font-medium text-gray-900 flex justify-between"},{default:W(()=>[De(n.$slots,"header"),A("button",{type:"button",class:"bg-white rounded-md text-gray-400 hover:text-gray-500",onClick:r},[qt,z(k(Nt),{class:"h-6 w-6","aria-hidden":"true"})])]),_:3})]),A("div",Wt,[De(n.$slots,"default")])])]),_:3})]),_:3})])])]),_:3})]),_:3},8,["show"]))}};export{ut as O,Qt as _,le as a,ge as f,Q as m,st as y};