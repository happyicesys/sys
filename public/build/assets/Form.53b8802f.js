import{g as v,T as l,h,c as x,a,w as r,p as y,o as i,b as s,f as m,l as b,t as w,d as C,u as f,e as S}from"./app.8dfb483a.js";import{_}from"./Button.0b5b1d9a.js";import{_ as k}from"./FormInput.a614dbc4.js";import{_ as $}from"./Modal.c3620c5c.js";import{r as V}from"./ArrowUturnLeftIcon.41207353.js";import{r as B}from"./CheckCircleIcon.42962ec5.js";import"./open-closed.cdb7e47f.js";import"./disposables.1ad74aa0.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},j=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},P=s("span",null," Back ",-1),U=s("span",null," Save ",-1),Q={__name:"Form",props:{permission:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:p}){const o=d,e=v(l(c()));h(()=>{e.value=o.permission?l(o.permission):l(c())});function c(){return{name:""}}function g(){e.value.clearErrors(),o.type==="create"&&e.value.post("/permissions/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),o.type==="update"&&e.value.post("/permissions/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(i(),x(y,{to:"body"},[a($,{open:d.showModal,onModalClose:t[2]||(t[2]=n=>u.$emit("modalClose"))},{header:r(()=>[s("div",N,[o.permission?(i(),m("span",M," Editing ")):b("",!0),o.permission?(i(),m("span",E,w(o.permission.name),1)):(i(),m("span",T," Create New Permission "))])]),default:r(()=>[s("form",{onSubmit:S(g,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[1]||(t[1]=n=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(V),{class:"w-4 h-4"}),P]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),U]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{Q as default};
