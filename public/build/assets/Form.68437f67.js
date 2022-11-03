import{i as v,u as c,j as h,o as l,c as x,a,w as r,T as y,d as s,g as i,p as b,t as w,b as f,e as C,f as S}from"./app.860330db.js";import{_}from"./Button.70d31857.js";import{_ as k,a as $,r as V}from"./Modal.4f3648ba.js";import{r as B}from"./ArrowUturnLeftIcon.4c809f2b.js";import"./open-closed.dd83d023.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},q=S(" Name "),O={class:"sm:col-span-6"},U={class:"flex space-x-1 mt-5 justify-end"},z=s("span",null," Back ",-1),A=s("span",null," Save ",-1),L={__name:"Form",props:{telco:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:m}){const t=d,e=v(c(u()));h(()=>{e.value=t.telco?c(t.telco):c(u())});function u(){return{name:""}}function g(){e.value.clearErrors(),t.type==="create"&&e.value.post("/telcos/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/telcos/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,o)=>(l(),x(y,{to:"body"},[a(k,{open:d.showModal,onModalClose:o[2]||(o[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[t.telco?(l(),i("span",M," Editing ")):b("",!0),t.telco?(l(),i("span",T,w(t.telco.name),1)):(l(),i("span",j," Create New Telco "))])]),default:r(()=>[s("form",{onSubmit:C(g,["prevent"]),id:"submit"},[s("div",F,[s("div",D,[a($,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),s("div",O,[s("div",U,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[1]||(o[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),z]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),A]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{L as default};
