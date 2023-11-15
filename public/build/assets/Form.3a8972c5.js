import{g,T as i,h as v,c as h,a as o,w as r,p as k,o as n,b as s,f as d,l as x,t as y,d as w,u as f,e as C}from"./app.baff8b32.js";import{_}from"./Button.ccd018db.js";import{_ as S}from"./FormInput.986fd714.js";import{_ as $}from"./Modal.3fbc64a3.js";import{r as B}from"./ArrowUturnLeftIcon.ef3b9238.js";import{r as V}from"./CheckCircleIcon.a59a4ba5.js";import"./open-closed.39849135.js";import"./disposables.b1ba51d4.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},j=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),Q={__name:"Form",props:{bank:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const a=m,e=g(i(u()));v(()=>{e.value=a.bank?i(a.bank):i(u())});function u(){return{name:""}}function b(){e.value.clearErrors(),a.type==="create"&&e.value.post("/banks/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/banks/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(n(),h(k,{to:"body"},[o($,{open:m.showModal,onModalClose:t[2]||(t[2]=l=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[a.bank?(n(),d("span",M," Editing ")):x("",!0),a.bank?(n(),d("span",E,y(a.bank.name),1)):(n(),d("span",T," Create New Bank "))])]),default:r(()=>[s("form",{onSubmit:C(b,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[o(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[w(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),U]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),z]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{Q as default};
