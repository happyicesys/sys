import{g as x,T as l,h,c as y,a,w as r,p as b,o as i,b as s,f as m,l as w,t as C,d as k,u as f,e as S}from"./app.242e5fba.js";import{_}from"./Button.be945d63.js";import{_ as $}from"./FormInput.15b0f65d.js";import{_ as V}from"./Modal.c8b43892.js";import{r as B}from"./ArrowUturnLeftIcon.88830442.js";import{r as N}from"./CheckCircleIcon.a28321ba.js";import"./keyboard.034c1cc1.js";import"./disposables.a3cad5e2.js";const M={class:"flex flex-col md:flex-row space-x-2"},E={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},P=s("span",null," Back ",-1),U=s("span",null," Save ",-1),Q={__name:"Form",props:{permission:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:g}){const o=d,p=g,e=x(l(c()));h(()=>{e.value=o.permission?l(o.permission):l(c())});function c(){return{name:""}}function v(){e.value.clearErrors(),o.type==="create"&&e.value.post("/permissions/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/permissions/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(i(),y(b,{to:"body"},[a(V,{open:d.showModal,onModalClose:t[2]||(t[2]=n=>u.$emit("modalClose"))},{header:r(()=>[s("div",M,[o.permission?(i(),m("span",E," Editing ")):w("",!0),o.permission?(i(),m("span",T,C(o.permission.name),1)):(i(),m("span",j," Create New Permission "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[a($,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[k(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),P]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(N),{class:"w-4 h-4"}),U]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};
