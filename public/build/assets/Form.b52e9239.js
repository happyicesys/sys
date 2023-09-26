import{g as v,T as i,h,c as x,a,w as r,q as y,o as n,b as s,f as d,l as b,t as w,d as C,u as f,e as S}from"./app.2e671246.js";import{_}from"./Button.6e95bfad.js";import{_ as k}from"./FormInput.057612aa.js";import{_ as z}from"./Modal.01f30ff8.js";import{r as $}from"./ArrowUturnLeftIcon.4f8563da.js";import{r as V}from"./CheckCircleIcon.84dcd428.js";import"./open-closed.94268395.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},E={key:2,class:"text-gray-600"},T=["onSubmit"],j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"sm:col-span-6"},D={class:"sm:col-span-6"},F={class:"flex space-x-1 mt-5 justify-end"},O=s("span",null," Back ",-1),U=s("span",null," Save ",-1),L={__name:"Form",props:{zone:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const o=m,e=v(i(u()));h(()=>{e.value=o.zone?i(o.zone):i(u())});function u(){return{name:""}}function g(){e.value.clearErrors(),o.type==="create"&&e.value.post("/zones/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/zones/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(n(),x(y,{to:"body"},[a(z,{open:m.showModal,onModalClose:t[2]||(t[2]=l=>p.$emit("modalClose"))},{header:r(()=>[s("div",B,[o.zone?(n(),d("span",N," Editing ")):b("",!0),o.zone?(n(),d("span",M,w(o.zone.name),1)):(n(),d("span",E," Create New Zone "))])]),default:r(()=>[s("form",{onSubmit:S(g,["prevent"]),id:"submit"},[s("div",j,[s("div",q,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",D,[s("div",F,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f($),{class:"w-4 h-4"}),O]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),U]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{L as default};