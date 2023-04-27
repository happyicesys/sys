import{h as g,T as i,j as h,c as x,a as o,w as r,s as y,o as l,b as s,f as d,m as b,t as w,d as C,u as f,e as S}from"./app.4afcab37.js";import{_}from"./Button.2754726f.js";import{_ as k}from"./FormInput.4a87e227.js";import{_ as $}from"./Modal.8fcc4011.js";import{r as V}from"./ArrowUturnLeftIcon.cfae3cbb.js";import{r as B}from"./CheckCircleIcon.e8eb5326.js";import"./open-closed.bf81ab9b.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},T=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=s("span",null," Back ",-1),z=s("span",null," Save ",-1),P={__name:"Form",props:{status:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const t=u,e=g(i(c()));h(()=>{e.value=t.status?i(t.status):i(c())});function c(){return{name:""}}function v(){e.value.clearErrors(),t.type==="create"&&e.value.post("/statuses/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/statuses/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(l(),x(y,{to:"body"},[o($,{open:u.showModal,onModalClose:a[2]||(a[2]=n=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[t.status?(l(),d("span",M," Editing ")):b("",!0),t.status?(l(),d("span",j,w(t.status.name),1)):(l(),d("span",E," Create New Status "))])]),default:r(()=>[s("form",{onSubmit:S(v,["prevent"]),id:"submit"},[s("div",D,[s("div",F,[o(k,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),s("div",q,[s("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),U]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),z]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{P as default};
