import{i as v,u as i,j as h,o as n,c as x,a,w as r,T as y,d as s,g as d,p as b,t as w,b as f,e as C,f as S}from"./app.7b13628b.js";import{_}from"./Button.d69eb374.js";import{_ as k,a as z,r as $}from"./Modal.c96b1c99.js";import{r as V}from"./ArrowUturnLeftIcon.0837d764.js";import"./open-closed.8ceffd08.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},j={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},T={class:"sm:col-span-6"},D=S(" Name "),q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),Z=s("span",null," Save ",-1),K={__name:"Form",props:{zone:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(c,{emit:m}){const o=c,e=v(i(u()));h(()=>{e.value=o.zone?i(o.zone):i(u())});function u(){return{name:""}}function g(){e.value.clearErrors(),o.type==="create"&&e.value.post("/zones/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/zones/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(n(),x(y,{to:"body"},[a(k,{open:c.showModal,onModalClose:t[2]||(t[2]=l=>p.$emit("modalClose"))},{header:r(()=>[s("div",B,[o.zone?(n(),d("span",N," Editing ")):b("",!0),o.zone?(n(),d("span",M,w(o.zone.name),1)):(n(),d("span",j," Create New Zone "))])]),default:r(()=>[s("form",{onSubmit:C(g,["prevent"]),id:"submit"},[s("div",F,[s("div",T,[a(z,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[D]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),U]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f($),{class:"w-4 h-4"}),Z]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{K as default};