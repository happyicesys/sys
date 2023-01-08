import{i as h,u as c,j as x,o as n,c as y,a as t,w as a,T as C,d as s,g as d,p as b,t as w,b as _,e as V,f as v}from"./app.91e87e68.js";import{_ as f}from"./Button.3d25434e.js";import{_ as k}from"./FormInput.81e31b78.js";import{_ as S}from"./FormTextarea.e3fae768.js";import{_ as $}from"./Modal.417c9c4e.js";import{r as B}from"./ArrowUturnLeftIcon.ed763ba4.js";import{r as N}from"./CheckCircleIcon.a86f9970.js";import"./open-closed.55107466.js";const M={class:"flex flex-col md:flex-row space-x-2"},j={key:0,class:"text-gray-600"},E={key:1},F={key:2,class:"text-gray-600"},T=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},R={class:"sm:col-span-6"},U=v(" Name "),q={class:"sm:col-span-6"},O=v(" Remarks "),z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},G=s("span",null," Back ",-1),H=s("span",null," Save ",-1),Y={__name:"Form",props:{resourceCenter:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:u}){const r=i,e=h(c(m()));x(()=>{e.value=r.resourceCenter?c(r.resourceCenter):c(m())});function m(){return{name:""}}function g(){e.value.clearErrors(),r.type==="create"&&e.value.post("/resource-centers/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),r.type==="update"&&e.value.post("/resource-centers/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,o)=>(n(),y(C,{to:"body"},[t($,{open:i.showModal,onModalClose:o[3]||(o[3]=l=>p.$emit("modalClose"))},{header:a(()=>[s("div",M,[r.resourceCenter?(n(),d("span",j," Editing ")):b("",!0),r.resourceCenter?(n(),d("span",E,w(r.resourceCenter.name),1)):(n(),d("span",F," Create New Resource "))])]),default:a(()=>[s("form",{onSubmit:V(g,["prevent"]),id:"submit"},[s("div",D,[s("div",R,[t(k,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:a(()=>[U]),_:1},8,["modelValue","error"])]),s("div",q,[t(S,{modelValue:e.value.desc,"onUpdate:modelValue":o[1]||(o[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:a(()=>[O]),_:1},8,["modelValue","error"])])]),s("div",z,[s("div",A,[t(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[2]||(o[2]=l=>p.$emit("modalClose")),form:"submit"},{default:a(()=>[t(_(B),{class:"w-4 h-4"}),G]),_:1}),t(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:a(()=>[t(_(N),{class:"w-4 h-4"}),H]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{Y as default};
