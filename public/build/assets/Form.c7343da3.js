import{g as x,T as i,h,c as y,a,w as r,p as b,o as l,b as s,f as m,l as w,t as C,d as k,u as f,e as S}from"./app.772f9cda.js";import{_}from"./Button.7ae31b12.js";import{_ as $}from"./FormInput.d3af3c8a.js";import{_ as V}from"./Modal.83bd6b54.js";import{r as B}from"./ArrowUturnLeftIcon.8f9cbd8a.js";import{r as N}from"./CheckCircleIcon.5965dd04.js";import"./keyboard.95c1f932.js";import"./disposables.dca62706.js";const M={class:"flex flex-col md:flex-row space-x-2"},E={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},R=s("span",null," Back ",-1),U=s("span",null," Save ",-1),P={__name:"Form",props:{role:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:g}){const o=d,c=g,e=x(i(p()));h(()=>{e.value=o.role?i(o.role):i(p())});function p(){return{name:""}}function v(){e.value.clearErrors(),o.type==="create"&&e.value.post("/roles/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/roles/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(l(),y(b,{to:"body"},[a(V,{open:d.showModal,onModalClose:t[2]||(t[2]=n=>u.$emit("modalClose"))},{header:r(()=>[s("div",M,[o.role?(l(),m("span",E," Editing ")):w("",!0),o.role?(l(),m("span",T,C(o.role.name),1)):(l(),m("span",j," Create New Role "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[a($,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[k(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),R]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(N),{class:"w-4 h-4"}),U]),_:1})])])],32)]),_:1},8,["open"])]))}};export{P as default};