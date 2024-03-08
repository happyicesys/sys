import{g as x,T as i,h,c as y,a,w as r,p as b,o as n,b as s,f as m,l as w,t as C,d as k,u as f,e as z}from"./app.c4e47028.js";import{_}from"./Button.4631b684.js";import{_ as S}from"./FormInput.2fd92b17.js";import{_ as $}from"./Modal.9639af57.js";import{r as V}from"./ArrowUturnLeftIcon.8d3b347e.js";import{r as B}from"./CheckCircleIcon.3fae9752.js";import"./keyboard.58689cfa.js";import"./disposables.be045d92.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},q={class:"flex space-x-1 mt-5 justify-end"},O=s("span",null," Back ",-1),U=s("span",null," Save ",-1),P={__name:"Form",props:{zone:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:g}){const o=d,c=g,e=x(i(p()));h(()=>{e.value=o.zone?i(o.zone):i(p())});function p(){return{name:""}}function v(){e.value.clearErrors(),o.type==="create"&&e.value.post("/zones/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/zones/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(n(),y(b,{to:"body"},[a($,{open:d.showModal,onModalClose:t[2]||(t[2]=l=>u.$emit("modalClose"))},{header:r(()=>[s("div",N,[o.zone?(n(),m("span",M," Editing ")):w("",!0),o.zone?(n(),m("span",E,C(o.zone.name),1)):(n(),m("span",T," Create New Zone "))])]),default:r(()=>[s("form",{onSubmit:z(v,["prevent"]),id:"submit"},[s("div",j,[s("div",D,[a(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[k(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",F,[s("div",q,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),O]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),U]),_:1})])])],32)]),_:1},8,["open"])]))}};export{P as default};
