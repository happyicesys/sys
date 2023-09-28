import{g as v,T as i,h,c as x,a as o,w as r,q as y,o as l,b as s,f as d,l as b,t as w,d as C,u as f,e as S}from"./app.59109b94.js";import{_}from"./Button.4b437cae.js";import{_ as k}from"./FormInput.232fb7ab.js";import{_ as $}from"./Modal.b1566029.js";import{r as V}from"./ArrowUturnLeftIcon.41bfcd1b.js";import{r as B}from"./CheckCircleIcon.93561378.js";import"./open-closed.8ef792f7.js";import"./platform.c10d09b7.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},j=["onSubmit"],q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),Q={__name:"Form",props:{status:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const t=u,e=v(i(c()));h(()=>{e.value=t.status?i(t.status):i(c())});function c(){return{name:""}}function g(){e.value.clearErrors(),t.type==="create"&&e.value.post("/statuses/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/statuses/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(l(),x(y,{to:"body"},[o($,{open:u.showModal,onModalClose:a[2]||(a[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[t.status?(l(),d("span",M," Editing ")):b("",!0),t.status?(l(),d("span",E,w(t.status.name),1)):(l(),d("span",T," Create New Status "))])]),default:r(()=>[s("form",{onSubmit:S(g,["prevent"]),id:"submit"},[s("div",q,[s("div",D,[o(k,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",F,[s("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),U]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),z]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{Q as default};