import{h as g,T as i,j as h,c as x,a,w as r,s as y,o as l,b as s,f as d,m as b,t as w,d as C,u as f,e as S}from"./app.ca55ebc0.js";import{_}from"./Button.a3688387.js";import{_ as k}from"./FormInput.52b40f2b.js";import{_ as $}from"./Modal.a5e3a924.js";import{r as V}from"./ArrowUturnLeftIcon.084cc096.js";import{r as B}from"./CheckCircleIcon.c675d3d5.js";import"./open-closed.48a41579.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},T=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},R=s("span",null," Back ",-1),U=s("span",null," Save ",-1),L={__name:"Form",props:{role:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:c}){const o=m,e=g(i(u()));h(()=>{e.value=o.role?i(o.role):i(u())});function u(){return{name:""}}function v(){e.value.clearErrors(),o.type==="create"&&e.value.post("/roles/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/roles/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,t)=>(l(),x(y,{to:"body"},[a($,{open:m.showModal,onModalClose:t[2]||(t[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[o.role?(l(),d("span",M," Editing ")):b("",!0),o.role?(l(),d("span",j,w(o.role.name),1)):(l(),d("span",E," Create New Role "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),R]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),U]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{L as default};