import{H as E,y as A,F as $,C as k,B as S}from"./app.bc1dddf3.js";let P=0;function C(){return++P}function F(){return C()}function L(e){var t;if(e==null||e.value==null)return null;let n=(t=e.value.$el)!=null?t:e.value;return n instanceof Node?n:null}function b(e,t,...n){if(e in t){let r=t[e];return typeof r=="function"?r(...n):r}let o=new Error(`Tried to handle "${e}" but there is no handler defined. Only defined handlers are: ${Object.keys(t).map(r=>`"${r}"`).join(", ")}.`);throw Error.captureStackTrace&&Error.captureStackTrace(o,b),o}var T=(e=>(e[e.None=0]="None",e[e.RenderStrategy=1]="RenderStrategy",e[e.Static=2]="Static",e))(T||{}),D=(e=>(e[e.Unmount=0]="Unmount",e[e.Hidden=1]="Hidden",e))(D||{});function W({visible:e=!0,features:t=0,ourProps:n,theirProps:o,...r}){var s;let i=j(o,n),u=Object.assign(r,{props:i});if(e||t&2&&i.static)return y(u);if(t&1){let f=(s=i.unmount)==null||s?0:1;return b(f,{[0](){return null},[1](){return y({...r,props:{...i,hidden:!0,style:{display:"none"}}})}})}return y(u)}function y({props:e,attrs:t,slots:n,slot:o,name:r}){var s,i;let{as:u,...f}=H(e,["unmount","static"]),l=(s=n.default)==null?void 0:s.call(n,o),g={};if(o){let c=!1,h=[];for(let[d,p]of Object.entries(o))typeof p=="boolean"&&(c=!0),p===!0&&h.push(d);c&&(g["data-headlessui-state"]=h.join(" "))}if(u==="template"){if(l=w(l!=null?l:[]),Object.keys(f).length>0||Object.keys(t).length>0){let[c,...h]=l!=null?l:[];if(!U(c)||h.length>0)throw new Error(['Passing props on "template"!',"",`The current component <${r} /> is rendering a "template".`,"However we need to passthrough the following props:",Object.keys(f).concat(Object.keys(t)).map(a=>a.trim()).filter((a,m,v)=>v.indexOf(a)===m).sort((a,m)=>a.localeCompare(m)).map(a=>`  - ${a}`).join(`
`),"","You can apply a few solutions:",['Add an `as="..."` prop, to ensure that we render an actual element instead of a "template".',"Render a single element as the child so that we can forward the props onto that element."].map(a=>`  - ${a}`).join(`
`)].join(`
`));let d=j((i=c.props)!=null?i:{},f,g),p=E(c,d,!0);for(let a in d)a.startsWith("on")&&(p.props||(p.props={}),p.props[a]=d[a]);return p}return Array.isArray(l)&&l.length===1?l[0]:l}return A(u,Object.assign({},f,g),{default:()=>l})}function w(e){return e.flatMap(t=>t.type===$?w(t.children):[t])}function j(...e){if(e.length===0)return{};if(e.length===1)return e[0];let t={},n={};for(let o of e)for(let r in o)r.startsWith("on")&&typeof o[r]=="function"?(n[r]!=null||(n[r]=[]),n[r].push(o[r])):t[r]=o[r];if(t.disabled||t["aria-disabled"])return Object.assign(t,Object.fromEntries(Object.keys(n).map(o=>[o,void 0])));for(let o in n)Object.assign(t,{[o](r,...s){let i=n[o];for(let u of i){if(r instanceof Event&&r.defaultPrevented)return;u(r,...s)}}});return t}function M(e){let t=Object.assign({},e);for(let n in t)t[n]===void 0&&delete t[n];return t}function H(e,t=[]){let n=Object.assign({},e);for(let o of t)o in n&&delete n[o];return n}function U(e){return e==null?!1:typeof e.type=="string"||typeof e.type=="object"||typeof e.type=="function"}let O=Symbol("Context");var N=(e=>(e[e.Open=1]="Open",e[e.Closed=2]="Closed",e[e.Closing=4]="Closing",e[e.Opening=8]="Opening",e))(N||{});function V(){return R()!==null}function R(){return k(O,null)}function Y(e){S(O,e)}var x=(e=>(e.Space=" ",e.Enter="Enter",e.Escape="Escape",e.Backspace="Backspace",e.Delete="Delete",e.ArrowLeft="ArrowLeft",e.ArrowUp="ArrowUp",e.ArrowRight="ArrowRight",e.ArrowDown="ArrowDown",e.Home="Home",e.End="End",e.PageUp="PageUp",e.PageDown="PageDown",e.Tab="Tab",e))(x||{});export{W as A,M as E,T as N,D as S,H as T,x as a,Y as b,N as i,R as l,L as o,V as s,F as t,b as u};