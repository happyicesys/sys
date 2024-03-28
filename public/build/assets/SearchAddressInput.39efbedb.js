import{e as v,o as w,t as x,l as y,a as k,Z as S}from"./combobox.b87c525d.js";import{r as V}from"./MagnifyingGlassCircleIcon.b67d50ca.js";import{g as B,o as s,c as i,w as l,b as r,a as n,u as t,r as C,f as m,l as f,n as A,F as D,k as N,t as I}from"./app.8898d4aa.js";const q={class:"flex space-x-1"},z={key:0,class:"text-red-500"},E={class:"relative mt-1"},F=["onClick"],Y={class:"block truncate"},L={__name:"SearchAddressInput",props:{disabled:[Boolean,String,Number],modelValue:String,required:[Boolean,String]},emits:["update:modelValue","selected"],setup(o,{emit:g}){const c=g,d=B([]),p=_.debounce(async e=>{const u="https://www.onemap.gov.sg/api/common/elastic/search?searchVal="+e.target.value+"&returnGeom=Y&getAddrDetails=Y";let a=await(await fetch(u)).json();a&&(d.value=await a.results)},300);function h(e){c("update:modelValue",e.target.value),p(e)}function b(e){c("selected",e)}return(e,u)=>(s(),i(t(S),{as:"div"},{default:l(()=>[r("div",q,[n(t(v),{class:"block text-sm font-medium text-gray-700"},{default:l(()=>[C(e.$slots,"default")]),_:3}),o.required?(s(),m("span",z," * ")):f("",!0)]),r("div",E,[n(t(w),{class:A(["w-full rounded-md border border-gray-300 py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",[o.disabled?"bg-gray-200":"bg-white"]]),onInput:h,value:o.modelValue,disabled:o.disabled},null,8,["value","disabled","class"]),n(t(x),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:l(()=>[n(t(V),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),d.value.length>0?(s(),i(t(y),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:l(()=>[(s(!0),m(D,null,N(d.value,a=>(s(),i(t(k),{as:"template"},{default:l(()=>[r("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:Z=>b(a)},[r("span",Y,I(a.ADDRESS),1)],8,F)]),_:2},1024))),256))]),_:1})):f("",!0)])]),_:3}))}};export{L as _};