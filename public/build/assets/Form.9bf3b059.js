import{g as h,T as d,h as b,c as w,a as o,w as r,p as C,o as n,b as t,f as i,l as V,t as k,d as f,u as _,e as S}from"./app.242e5fba.js";import{_ as x}from"./Button.be945d63.js";import{_ as v}from"./FormInput.15b0f65d.js";import{_ as $}from"./Modal.c8b43892.js";import{r as B}from"./ArrowUturnLeftIcon.88830442.js";import{r as N}from"./CheckCircleIcon.a28321ba.js";import"./keyboard.034c1cc1.js";import"./disposables.a3cad5e2.js";const M={class:"flex flex-col md:flex-row space-x-2"},T={key:0,class:"text-gray-600"},E={key:1},j={key:2,class:"text-gray-600"},q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},R=t("span",null," Back ",-1),z=t("span",null," Save ",-1),Q={__name:"Form",props:{tax:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:g}){const s=m,u=g,e=h(d(c()));b(()=>{e.value=s.tax?d(s.tax):d(c())});function c(){return{name:"",rate:""}}function y(){e.value.clearErrors(),s.type==="create"&&e.value.post("/taxes/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&e.value.post("/taxes/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(n(),w(C,{to:"body"},[o($,{open:m.showModal,onModalClose:a[3]||(a[3]=l=>p.$emit("modalClose"))},{header:r(()=>[t("div",M,[s.tax?(n(),i("span",T," Editing ")):V("",!0),s.tax?(n(),i("span",E,k(s.tax.name),1)):(n(),i("span",j," Create New Tax "))])]),default:r(()=>[t("form",{onSubmit:S(y,["prevent"]),id:"submit"},[t("div",q,[t("div",D,[o(v,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),t("div",F,[o(v,{modelValue:e.value.rate,"onUpdate:modelValue":a[1]||(a[1]=l=>e.value.rate=l),error:e.value.errors.rate,required:"true"},{default:r(()=>[f(" Rate ")]),_:1},8,["modelValue","error"])])]),t("div",U,[t("div",O,[o(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[2]||(a[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(_(B),{class:"w-4 h-4"}),R]),_:1}),o(x,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(_(N),{class:"w-4 h-4"}),z]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};
