import{i as g,u as i,j as v,o as n,c as h,a as o,w as r,T as k,d as s,g as d,p as x,t as y,b as f,e as w,f as C}from"./app.7e392996.js";import{_}from"./Button.a4bc7dc6.js";import{_ as S}from"./FormInput.c9fc7f3a.js";import{_ as $}from"./Modal.7bb3d2e4.js";import{r as B}from"./ArrowUturnLeftIcon.52ddfe23.js";import{r as V}from"./CheckCircleIcon.e8e6b0bc.js";import"./open-closed.dc611207.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=C(" Name "),O={class:"sm:col-span-6"},U={class:"flex space-x-1 mt-5 justify-end"},z=s("span",null," Back ",-1),A=s("span",null," Save ",-1),Q={__name:"Form",props:{bank:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const a=m,e=g(i(u()));v(()=>{e.value=a.bank?i(a.bank):i(u())});function u(){return{name:""}}function b(){e.value.clearErrors(),a.type==="create"&&e.value.post("/banks/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/banks/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(n(),h(k,{to:"body"},[o($,{open:m.showModal,onModalClose:t[2]||(t[2]=l=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[a.bank?(n(),d("span",M," Editing ")):x("",!0),a.bank?(n(),d("span",j,y(a.bank.name),1)):(n(),d("span",E," Create New Bank "))])]),default:r(()=>[s("form",{onSubmit:w(b,["prevent"]),id:"submit"},[s("div",T,[s("div",D,[o(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",U,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),z]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),A]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{Q as default};
