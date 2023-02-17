import{i as v,u as l,j as h,o as i,c as x,a,w as r,T as y,d as s,g as m,p as b,t as w,b as f,e as C,f as S}from"./app.a63aabc8.js";import{_}from"./Button.ff33d227.js";import{_ as k}from"./FormInput.442482d3.js";import{_ as $}from"./Modal.b859bd9a.js";import{r as V}from"./ArrowUturnLeftIcon.e47cbff1.js";import{r as B}from"./CheckCircleIcon.88bbb0a6.js";import"./open-closed.c20b8099.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=S(" Name "),O={class:"sm:col-span-6"},P={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),Q={__name:"Form",props:{permission:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:c}){const o=d,e=v(l(p()));h(()=>{e.value=o.permission?l(o.permission):l(p())});function p(){return{name:""}}function g(){e.value.clearErrors(),o.type==="create"&&e.value.post("/permissions/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/permissions/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(i(),x(y,{to:"body"},[a($,{open:d.showModal,onModalClose:t[2]||(t[2]=n=>u.$emit("modalClose"))},{header:r(()=>[s("div",N,[o.permission?(i(),m("span",M," Editing ")):b("",!0),o.permission?(i(),m("span",j,w(o.permission.name),1)):(i(),m("span",E," Create New Permission "))])]),default:r(()=>[s("form",{onSubmit:C(g,["prevent"]),id:"submit"},[s("div",T,[s("div",D,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",P,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),U]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),z]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{Q as default};