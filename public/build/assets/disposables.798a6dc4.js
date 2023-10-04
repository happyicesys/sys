import{o as y,u as F,H as N}from"./open-closed.aa6761c3.js";import{q as b,g as x,j as P,s as L}from"./app.f3f8417a.js";var A=Object.defineProperty,S=(e,t,n)=>t in e?A(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n,E=(e,t,n)=>(S(e,typeof t!="symbol"?t+"":t,n),n);class O{constructor(){E(this,"current",this.detect()),E(this,"currentId",0)}set(t){this.current!==t&&(this.currentId=0,this.current=t)}reset(){this.set(this.detect())}nextId(){return++this.currentId}get isServer(){return this.current==="server"}get isClient(){return this.current==="client"}detect(){return typeof window>"u"||typeof document>"u"?"server":"client"}}let w=new O;function I(e){if(w.isServer)return null;if(e instanceof Node)return e.ownerDocument;if(e!=null&&e.hasOwnProperty("value")){let t=y(e);if(t)return t.ownerDocument}return document}let h=["[contentEditable=true]","[tabindex]","a[href]","area[href]","button:not([disabled])","iframe","input:not([disabled])","select:not([disabled])","textarea:not([disabled])"].map(e=>`${e}:not([tabindex='-1'])`).join(",");var M=(e=>(e[e.First=1]="First",e[e.Previous=2]="Previous",e[e.Next=4]="Next",e[e.Last=8]="Last",e[e.WrapAround=16]="WrapAround",e[e.NoScroll=32]="NoScroll",e))(M||{}),T=(e=>(e[e.Error=0]="Error",e[e.Overflow=1]="Overflow",e[e.Success=2]="Success",e[e.Underflow=3]="Underflow",e))(T||{}),$=(e=>(e[e.Previous=-1]="Previous",e[e.Next=1]="Next",e))($||{});function H(e=document.body){return e==null?[]:Array.from(e.querySelectorAll(h)).sort((t,n)=>Math.sign((t.tabIndex||Number.MAX_SAFE_INTEGER)-(n.tabIndex||Number.MAX_SAFE_INTEGER)))}var g=(e=>(e[e.Strict=0]="Strict",e[e.Loose=1]="Loose",e))(g||{});function D(e,t=0){var n;return e===((n=I(e))==null?void 0:n.body)?!1:F(t,{[0](){return e.matches(h)},[1](){let r=e;for(;r!==null;){if(r.matches(h))return!0;r=r.parentElement}return!1}})}var q=(e=>(e[e.Keyboard=0]="Keyboard",e[e.Mouse=1]="Mouse",e))(q||{});typeof window<"u"&&typeof document<"u"&&(document.addEventListener("keydown",e=>{e.metaKey||e.altKey||e.ctrlKey||(document.documentElement.dataset.headlessuiFocusVisible="")},!0),document.addEventListener("click",e=>{e.detail===1?delete document.documentElement.dataset.headlessuiFocusVisible:e.detail===0&&(document.documentElement.dataset.headlessuiFocusVisible="")},!0));function z(e){e==null||e.focus({preventScroll:!0})}let _=["textarea","input"].join(",");function j(e){var t,n;return(n=(t=e==null?void 0:e.matches)==null?void 0:t.call(e,_))!=null?n:!1}function k(e,t=n=>n){return e.slice().sort((n,r)=>{let u=t(n),o=t(r);if(u===null||o===null)return 0;let s=u.compareDocumentPosition(o);return s&Node.DOCUMENT_POSITION_FOLLOWING?-1:s&Node.DOCUMENT_POSITION_PRECEDING?1:0})}function B(e,t,{sorted:n=!0,relativeTo:r=null,skipElements:u=[]}={}){var o;let s=(o=Array.isArray(e)?e.length>0?e[0].ownerDocument:document:e==null?void 0:e.ownerDocument)!=null?o:document,i=Array.isArray(e)?n?k(e):e:H(e);u.length>0&&i.length>1&&(i=i.filter(d=>!u.includes(d))),r=r!=null?r:s.activeElement;let p=(()=>{if(t&5)return 1;if(t&10)return-1;throw new Error("Missing Focus.First, Focus.Previous, Focus.Next or Focus.Last")})(),a=(()=>{if(t&1)return 0;if(t&2)return Math.max(0,i.indexOf(r))-1;if(t&4)return Math.max(0,i.indexOf(r))+1;if(t&8)return i.length-1;throw new Error("Missing Focus.First, Focus.Previous, Focus.Next or Focus.Last")})(),l=t&32?{preventScroll:!0}:{},m=0,f=i.length,c;do{if(m>=f||m+f<=0)return 0;let d=a+m;if(t&16)d=(d+f)%f;else{if(d<0)return 3;if(d>=f)return 1}c=i[d],c==null||c.focus(l),m+=p}while(c!==s.activeElement);return t&6&&j(c)&&c.select(),2}function v(e,t,n){w.isServer||b(r=>{document.addEventListener(e,t,n),r(()=>document.removeEventListener(e,t,n))})}function C(e,t,n){w.isServer||b(r=>{window.addEventListener(e,t,n),r(()=>window.removeEventListener(e,t,n))})}function J(e,t,n=P(()=>!0)){function r(o,s){if(!n.value||o.defaultPrevented)return;let i=s(o);if(i===null||!i.getRootNode().contains(i))return;let p=function a(l){return typeof l=="function"?a(l()):Array.isArray(l)||l instanceof Set?l:[l]}(e);for(let a of p){if(a===null)continue;let l=a instanceof HTMLElement?a:y(a);if(l!=null&&l.contains(i)||o.composed&&o.composedPath().includes(l))return}return!D(i,g.Loose)&&i.tabIndex!==-1&&o.preventDefault(),t(o,i)}let u=x(null);v("pointerdown",o=>{var s,i;n.value&&(u.value=((i=(s=o.composedPath)==null?void 0:s.call(o))==null?void 0:i[0])||o.target)},!0),v("mousedown",o=>{var s,i;n.value&&(u.value=((i=(s=o.composedPath)==null?void 0:s.call(o))==null?void 0:i[0])||o.target)},!0),v("click",o=>{u.value&&(r(o,()=>u.value),u.value=null)},!0),v("touchend",o=>r(o,()=>o.target instanceof HTMLElement?o.target:null),!0),C("blur",o=>r(o,()=>window.document.activeElement instanceof HTMLIFrameElement?window.document.activeElement:null),!0)}var K=(e=>(e[e.None=1]="None",e[e.Focusable=2]="Focusable",e[e.Hidden=4]="Hidden",e))(K||{});let Q=L({name:"Hidden",props:{as:{type:[Object,String],default:"div"},features:{type:Number,default:1}},setup(e,{slots:t,attrs:n}){return()=>{let{features:r,...u}=e,o={"aria-hidden":(r&2)===2?!0:void 0,style:{position:"fixed",top:1,left:1,width:1,height:0,padding:0,margin:-1,overflow:"hidden",clip:"rect(0, 0, 0, 0)",whiteSpace:"nowrap",borderWidth:"0",...(r&4)===4&&(r&2)!==2&&{display:"none"}}};return N({ourProps:o,theirProps:u,slot:{},attrs:n,slots:t,name:"Hidden"})}}});function G(){return/iPhone/gi.test(window.navigator.platform)||/Mac/gi.test(window.navigator.platform)&&window.navigator.maxTouchPoints>0}function R(){return/Android/gi.test(window.navigator.userAgent)}function Y(){return G()||R()}function U(e){typeof queueMicrotask=="function"?queueMicrotask(e):Promise.resolve().then(e).catch(t=>setTimeout(()=>{throw t}))}function V(){let e=[],t={addEventListener(n,r,u,o){return n.addEventListener(r,u,o),t.add(()=>n.removeEventListener(r,u,o))},requestAnimationFrame(...n){let r=requestAnimationFrame(...n);t.add(()=>cancelAnimationFrame(r))},nextFrame(...n){t.requestAnimationFrame(()=>{t.requestAnimationFrame(...n)})},setTimeout(...n){let r=setTimeout(...n);t.add(()=>clearTimeout(r))},microTask(...n){let r={current:!0};return U(()=>{r.current&&n[0]()}),t.add(()=>{r.current=!1})},style(n,r,u){let o=n.style.getPropertyValue(r);return Object.assign(n.style,{[r]:u}),this.add(()=>{Object.assign(n.style,{[r]:o})})},group(n){let r=V();return n(r),this.add(()=>r.dispose())},add(n){return e.push(n),()=>{let r=e.indexOf(n);if(r>=0)for(let u of e.splice(r,1))u()}},dispose(){for(let n of e.splice(0))n()}};return t}export{M as N,k as O,B as P,z as S,T,K as a,G as b,w as c,Q as f,I as m,Y as n,V as o,U as t,C as w,J as y};
