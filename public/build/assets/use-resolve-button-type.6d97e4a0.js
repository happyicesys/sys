import{o as u}from"./open-closed.f3e9263d.js";import{i as r,j as l,x as f}from"./app.85f64675.js";function a(t,n){if(t)return t;let e=n!=null?n:"button";if(typeof e=="string"&&e.toLowerCase()==="button")return"button"}function v(t,n){let e=r(a(t.value.type,t.value.as));return l(()=>{e.value=a(t.value.type,t.value.as)}),f(()=>{var o;e.value||!u(n)||u(n)instanceof HTMLButtonElement&&!((o=u(n))!=null&&o.hasAttribute("type"))&&(e.value="button")}),e}export{v as b};