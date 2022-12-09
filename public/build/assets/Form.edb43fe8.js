import{i as v,u as i,j as h,o as l,c as x,a as o,w as r,T as y,d as s,g as d,p as b,t as w,b as f,e as C,f as S}from"./app.7e392996.js";import{_}from"./Button.a4bc7dc6.js";import{_ as k}from"./FormInput.c9fc7f3a.js";import{_ as $}from"./Modal.7bb3d2e4.js";import{r as V}from"./ArrowUturnLeftIcon.52ddfe23.js";import{r as B}from"./CheckCircleIcon.e8e6b0bc.js";import"./open-closed.dc611207.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=S(" Name "),O={class:"sm:col-span-6"},U={class:"flex space-x-1 mt-5 justify-end"},z=s("span",null," Back ",-1),A=s("span",null," Save ",-1),Q={__name:"Form",props:{tag:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const t=m,e=v(i(u()));h(()=>{e.value=t.tag?i(t.tag):i(u())});function u(){return{name:""}}function g(){e.value.clearErrors(),t.type==="create"&&e.value.post("/tags/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/tags/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(l(),x(y,{to:"body"},[o($,{open:m.showModal,onModalClose:a[2]||(a[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[t.tag?(l(),d("span",M," Editing ")):b("",!0),t.tag?(l(),d("span",T,w(t.tag.name),1)):(l(),d("span",j," Create New Tag "))])]),default:r(()=>[s("form",{onSubmit:C(g,["prevent"]),id:"submit"},[s("div",F,[s("div",D,[o(k,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",U,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),z]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),A]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{Q as default};
