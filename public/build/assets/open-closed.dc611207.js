import{I as b,z as w,F as j,D as O,C as v}from"./app.7e392996.js";function g(e,t,...o){if(e in t){let n=t[e];return typeof n=="function"?n(...o):n}let r=new Error(`Tried to handle "${e}" but there is no handler defined. Only defined handlers are: ${Object.keys(t).map(n=>`"${n}"`).join(", ")}.`);throw Error.captureStackTrace&&Error.captureStackTrace(r,g),r}var E=(e=>(e[e.None=0]="None",e[e.RenderStrategy=1]="RenderStrategy",e[e.Static=2]="Static",e))(E||{}),k=(e=>(e[e.Unmount=0]="Unmount",e[e.Hidden=1]="Hidden",e))(k||{});function H({visible:e=!0,features:t=0,ourProps:o,theirProps:r,...n}){var s;let l=A(r,o),i=Object.assign(n,{props:l});if(e||t&2&&l.static)return h(i);if(t&1){let a=(s=l.unmount)==null||s?0:1;return g(a,{[0](){return null},[1](){return h({...n,props:{...l,hidden:!0,style:{display:"none"}}})}})}return h(i)}function h({props:e,attrs:t,slots:o,slot:r,name:n}){var s;let{as:l,...i}=$(e,["unmount","static"]),a=(s=o.default)==null?void 0:s.call(o,r),d={};if(r){let c=!1,f=[];for(let[u,p]of Object.entries(r))typeof p=="boolean"&&(c=!0),p===!0&&f.push(u);c&&(d["data-headlessui-state"]=f.join(" "))}if(l==="template"){if(a=y(a),Object.keys(i).length>0||Object.keys(t).length>0){let[c,...f]=a!=null?a:[];if(!P(c)||f.length>0)throw new Error(['Passing props on "template"!',"",`The current component <${n} /> is rendering a "template".`,"However we need to passthrough the following props:",Object.keys(i).concat(Object.keys(t)).sort((u,p)=>u.localeCompare(p)).map(u=>`  - ${u}`).join(`
`),"","You can apply a few solutions:",['Add an `as="..."` prop, to ensure that we render an actual element instead of a "template".',"Render a single element as the child so that we can forward the props onto that element."].map(u=>`  - ${u}`).join(`
`)].join(`
`));return b(c,Object.assign({},i,d))}return Array.isArray(a)&&a.length===1?a[0]:a}return w(l,Object.assign({},i,d),a)}function y(e){return e.flatMap(t=>t.type===j?y(t.children):[t])}function A(...e){if(e.length===0)return{};if(e.length===1)return e[0];let t={},o={};for(let r of e)for(let n in r)n.startsWith("on")&&typeof r[n]=="function"?(o[n]!=null||(o[n]=[]),o[n].push(r[n])):t[n]=r[n];if(t.disabled||t["aria-disabled"])return Object.assign(t,Object.fromEntries(Object.keys(o).map(r=>[r,void 0])));for(let r in o)Object.assign(t,{[r](n,...s){let l=o[r];for(let i of l){if(n instanceof Event&&n.defaultPrevented)return;i(n,...s)}}});return t}function x(e){let t=Object.assign({},e);for(let o in t)t[o]===void 0&&delete t[o];return t}function $(e,t=[]){let o=Object.assign({},e);for(let r of t)r in o&&delete o[r];return o}function P(e){return e==null?!1:typeof e.type=="string"||typeof e.type=="object"||typeof e.type=="function"}let S=0;function D(){return++S}function N(){return D()}var R=(e=>(e.Space=" ",e.Enter="Enter",e.Escape="Escape",e.Backspace="Backspace",e.Delete="Delete",e.ArrowLeft="ArrowLeft",e.ArrowUp="ArrowUp",e.ArrowRight="ArrowRight",e.ArrowDown="ArrowDown",e.Home="Home",e.End="End",e.PageUp="PageUp",e.PageDown="PageDown",e.Tab="Tab",e))(R||{});function B(e){var t;return e==null||e.value==null?null:(t=e.value.$el)!=null?t:e.value}let m=Symbol("Context");var T=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(T||{});function F(){return U()!==null}function U(){return O(m,null)}function L(e){v(m,e)}export{k as O,x as P,E as R,H as V,R as a,L as c,F as f,T as l,B as o,U as p,N as t,g as u,$ as w};
