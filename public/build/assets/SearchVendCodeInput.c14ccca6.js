import{H as x,$ as b,K as y,r as k,U as w,_ as V,N as C}from"./MagnifyingGlassCircleIcon.83439c67.js";import{g as N,o as a,c as d,w as s,b as n,a as u,u as t,r as S,f as c,l as i,F as B,k as $,t as f}from"./app.024e39e5.js";const I={class:"flex space-x-1"},q={key:0,class:"text-red-500"},F={class:"relative mt-1"},H=["onClick"],K={class:"block truncate"},U={key:1,class:"text-sm text-red-600"},E={__name:"SearchVendCodeInput",props:{modelValue:[String,Number],required:[Boolean,String],error:String},emits:["update:modelValue","selected"],setup(r,{emit:m}){const o=N([]),h=_.debounce(async e=>{if(!e.target.value.length){o.value=[];return}axios({method:"get",url:"/api/vends/search/"+e.target.value}).then(l=>{o.value=l.data}).catch(l=>{console.log(l)})},300);function p(e){m("update:modelValue",e.target.value),h(e)}function v(e){m("selected",e)}return(e,l)=>(a(),d(t(C),{as:"div"},{default:s(()=>[n("div",I,[u(t(x),{class:"block text-sm font-medium text-gray-700"},{default:s(()=>[S(e.$slots,"default")]),_:3}),r.required?(a(),c("span",q," * ")):i("",!0)]),n("div",F,[u(t(b),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:p,value:r.modelValue},null,8,["value"]),u(t(y),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:s(()=>[u(t(k),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),o.value.length>0?(a(),d(t(w),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:s(()=>[(a(!0),c(B,null,$(o.value,g=>(a(),d(t(V),{as:"template"},{default:s(()=>[n("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:z=>v(g)},[n("span",K,f(g.code),1)],8,H)]),_:2},1024))),256))]),_:1})):i("",!0),r.error?(a(),c("div",U,f(r.error),1)):i("",!0)])]),_:3}))}};export{E as _};
