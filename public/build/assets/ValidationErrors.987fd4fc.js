import{g as _,h as m,o as t,f as s,t as l,r as p,j as u,Q as f,b as i,F as h,k as g,l as v}from"./app.c8734f48.js";const k=["value"],E={__name:"Input",props:["modelValue"],emits:["update:modelValue"],setup(o){const e=_(null);return m(()=>{e.value.hasAttribute("autofocus")&&e.value.focus()}),(a,r)=>(t(),s("input",{class:"border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm",value:o.modelValue,onInput:r[0]||(r[0]=n=>a.$emit("update:modelValue",n.target.value)),ref_key:"input",ref:e},null,40,k))}},y={class:"block font-medium text-sm text-gray-700"},b={key:0},$={key:1},S={__name:"Label",props:["value"],setup(o){return(e,a)=>(t(),s("label",y,[o.value?(t(),s("span",b,l(o.value),1)):(t(),s("span",$,[p(e.$slots,"default")]))]))}},x={key:0},V=i("div",{class:"font-medium text-red-600"},"Whoops! Something went wrong.",-1),w={class:"mt-3 list-disc list-inside text-sm text-red-600"},j={__name:"ValidationErrors",setup(o){const e=u(()=>f().props.errors),a=u(()=>Object.keys(e.value).length>0);return(r,n)=>a.value?(t(),s("div",x,[V,i("ul",w,[(t(!0),s(h,null,g(e.value,(c,d)=>(t(),s("li",{key:d},l(c),1))),128))])])):v("",!0)}};export{j as _,S as a,E as b};
