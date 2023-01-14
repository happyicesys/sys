import{i as v,u as i,j as h,o as l,c as x,a,w as r,T as y,d as s,g as d,p as b,t as w,b as f,e as C,f as S}from"./app.ad0996d0.js";import{_}from"./Button.1d76c393.js";import{_ as k}from"./FormInput.89a1716a.js";import{_ as $}from"./Modal.333b53d2.js";import{r as V}from"./ArrowUturnLeftIcon.92b36951.js";import{r as B}from"./CheckCircleIcon.9ddca09e.js";import"./open-closed.4c312123.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=S(" Name "),O={class:"sm:col-span-6"},R={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),P={__name:"Form",props:{role:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const o=m,e=v(i(u()));h(()=>{e.value=o.role?i(o.role):i(u())});function u(){return{name:""}}function g(){e.value.clearErrors(),o.type==="create"&&e.value.post("/roles/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/roles/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(l(),x(y,{to:"body"},[a($,{open:m.showModal,onModalClose:t[2]||(t[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[o.role?(l(),d("span",M," Editing ")):b("",!0),o.role?(l(),d("span",j,w(o.role.name),1)):(l(),d("span",E," Create New Role "))])]),default:r(()=>[s("form",{onSubmit:C(g,["prevent"]),id:"submit"},[s("div",T,[s("div",D,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",R,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),U]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),z]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{P as default};
