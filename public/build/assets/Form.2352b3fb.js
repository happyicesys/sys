import{g as b,T as i,h as k,c as x,a as o,w as r,p as h,o as n,b as s,f as m,l as y,t as w,d as C,u as f,e as S}from"./app.bc1dddf3.js";import{_}from"./Button.a83df21d.js";import{_ as $}from"./FormInput.fc254a12.js";import{_ as B}from"./Modal.0385fd05.js";import{r as V}from"./ArrowUturnLeftIcon.877efbe8.js";import{r as N}from"./CheckCircleIcon.e46eddcd.js";import"./keyboard.b5d8b71d.js";import"./disposables.bd0067a5.js";const M={class:"flex flex-col md:flex-row space-x-2"},E={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),Q={__name:"Form",props:{bank:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:g}){const a=d,c=g,e=b(i(p()));k(()=>{e.value=a.bank?i(a.bank):i(p())});function p(){return{name:""}}function v(){e.value.clearErrors(),a.type==="create"&&e.value.post("/banks/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/banks/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(n(),x(h,{to:"body"},[o(B,{open:d.showModal,onModalClose:t[2]||(t[2]=l=>u.$emit("modalClose"))},{header:r(()=>[s("div",M,[a.bank?(n(),m("span",E," Editing ")):y("",!0),a.bank?(n(),m("span",T,w(a.bank.name),1)):(n(),m("span",j," Create New Bank "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[o($,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),U]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(N),{class:"w-4 h-4"}),z]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};