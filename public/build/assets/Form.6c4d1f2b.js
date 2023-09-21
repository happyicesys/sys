import{g as h,T as i,h as g,c as x,a as t,w as r,q as y,o as l,b as s,f as d,l as b,t as C,d as w,u as f,e as S}from"./app.b4f9cb3b.js";import{_}from"./Button.b7f3d052.js";import{_ as k}from"./FormInput.84bfdd36.js";import{_ as $}from"./Modal.7acf9242.js";import{r as P}from"./ArrowUturnLeftIcon.38ee5ffa.js";import{r as V}from"./CheckCircleIcon.a103d60f.js";import"./open-closed.91dadfa3.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},E={key:2,class:"text-gray-600"},T=["onSubmit"],j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"sm:col-span-6"},D={class:"sm:col-span-6"},F={class:"flex space-x-1 mt-5 justify-end"},O=s("span",null," Back ",-1),U=s("span",null," Save ",-1),L={__name:"Form",props:{cashlessProvider:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(c,{emit:m}){const o=c,e=h(i(u()));g(()=>{e.value=o.cashlessProvider?i(o.cashlessProvider):i(u())});function u(){return{name:""}}function v(){e.value.clearErrors(),o.type==="create"&&e.value.post("/cashless-providers/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/cashless-providers/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(l(),x(y,{to:"body"},[t($,{open:c.showModal,onModalClose:a[2]||(a[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",B,[o.cashlessProvider?(l(),d("span",N," Editing ")):b("",!0),o.cashlessProvider?(l(),d("span",M,C(o.cashlessProvider.name),1)):(l(),d("span",E," Create New Cashless Provider "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",j,[s("div",q,[t(k,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[w(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",D,[s("div",F,[t(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[t(f(P),{class:"w-4 h-4"}),O]),_:1}),t(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[t(f(V),{class:"w-4 h-4"}),U]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{L as default};