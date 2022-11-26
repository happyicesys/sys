import{i as g,u as m,j as v,o as n,c as h,a as o,w as r,T as x,d as s,g as i,p as b,t as w,b as f,e as C,f as T}from"./app.c318dc46.js";import{_}from"./Button.27f88839.js";import{_ as S,a as k,r as $}from"./Modal.6db78482.js";import{r as V}from"./ArrowUturnLeftIcon.f4e69ee0.js";import"./open-closed.4eec9221.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},j={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=T(" Name "),O={class:"sm:col-span-6"},P={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),K={__name:"Form",props:{paymentTerm:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:c}){const t=d,e=g(m(p()));v(()=>{e.value=t.paymentTerm?m(t.paymentTerm):m(p())});function p(){return{name:""}}function y(){e.value.clearErrors(),t.type==="create"&&e.value.post("/payment-terms/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/payment-terms/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,a)=>(n(),h(x,{to:"body"},[o(S,{open:d.showModal,onModalClose:a[2]||(a[2]=l=>u.$emit("modalClose"))},{header:r(()=>[s("div",B,[t.paymentTerm?(n(),i("span",N," Editing ")):b("",!0),t.paymentTerm?(n(),i("span",M,w(t.paymentTerm.name),1)):(n(),i("span",j," Create New Payment Term "))])]),default:r(()=>[s("form",{onSubmit:C(y,["prevent"]),id:"submit"},[s("div",F,[s("div",D,[o(k,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",P,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),U]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f($),{class:"w-4 h-4"}),z]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{K as default};
