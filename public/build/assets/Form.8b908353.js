import{h as g,T as n,j as V,c as C,a as r,w as d,s as k,o as i,b as o,f as c,m as f,t as y,d as v,u as x,e as w}from"./app.5696c208.js";import{_ as h}from"./Button.05b52e08.js";import{_ as S}from"./FormInput.7040576b.js";import{_ as $}from"./FormTextarea.dc553fe3.js";import{_ as B}from"./Modal.85fa36f3.js";import{_ as N}from"./MultiSelect.fdaf5979.js";import{r as j}from"./ArrowUturnLeftIcon.5b6993a6.js";import{r as M}from"./CheckCircleIcon.221d7c86.js";import"./open-closed.33461f34.js";const G={class:"flex flex-col md:flex-row space-x-2"},D={key:0,class:"text-gray-600"},E={key:1},O={key:2,class:"text-gray-600"},T=["onSubmit"],U={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},P={class:"sm:col-span-6"},z=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Category Group ",-1),A={key:0,class:"text-sm text-red-600"},H={class:"sm:col-span-6"},I={class:"flex space-x-1 mt-5 justify-end"},J=o("span",null," Back ",-1),K=o("span",null," Save ",-1),te={__name:"Form",props:{category:Object,categoryGroups:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const a=u,e=g(n(_())),p=g([]);V(()=>{p.value=a.categoryGroups.data,e.value=a.category?n(a.category):n(_()),a.type==="create"&&(e.value.category_group_id=e.value.category_group_id?e.value.category_group_id:"")});function _(){return{name:"",desc:"",category_group_id:""}}function b(){e.value.clearErrors(),a.type==="create"&&e.value.transform(s=>({...s,category_group_id:s.category_group_id.id})).post("/categories/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.transform(s=>({...s,category_group_id:s.category_group_id.id})).post("/categories/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(s,t)=>(i(),C(k,{to:"body"},[r(B,{open:u.showModal,onModalClose:t[4]||(t[4]=l=>s.$emit("modalClose"))},{header:d(()=>[o("div",G,[a.category?(i(),c("span",D," Editing ")):f("",!0),a.category?(i(),c("span",E,y(a.category.name),1)):(i(),c("span",O," Create New Category "))])]),default:d(()=>[o("form",{onSubmit:w(b,["prevent"]),id:"submit"},[o("div",U,[o("div",F,[r(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:d(()=>[v(" Name ")]),_:1},8,["modelValue","error"])]),o("div",q,[r($,{modelValue:e.value.desc,"onUpdate:modelValue":t[1]||(t[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:d(()=>[v(" Desc ")]),_:1},8,["modelValue","error"])]),o("div",P,[z,r(N,{modelValue:e.value.category_group_id,"onUpdate:modelValue":t[2]||(t[2]=l=>e.value.category_group_id=l),options:p.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.category_group_id?(i(),c("div",A,y(e.value.errors.category_group_id),1)):f("",!0)])]),o("div",H,[o("div",I,[r(h,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[3]||(t[3]=l=>s.$emit("modalClose")),form:"submit"},{default:d(()=>[r(x(j),{class:"w-4 h-4"}),J]),_:1}),r(h,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[r(x(M),{class:"w-4 h-4"}),K]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{te as default};
