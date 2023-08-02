import{g,T as n,h as V,c as C,a as r,w as d,q as k,o as i,b as o,f as c,l as f,t as y,d as v,u as x,e as w}from"./app.7d502d37.js";import{_ as h}from"./Button.299af46d.js";import{_ as S}from"./FormInput.811513d1.js";import{_ as $}from"./FormTextarea.ca70722e.js";import{_ as B,r as N}from"./Modal.87e37d6a.js";import{_ as M}from"./MultiSelect.ea5fca8d.js";import{r as j}from"./ArrowUturnLeftIcon.5dee7dc0.js";import"./open-closed.7bf95799.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.29e84cf9.js";const G={class:"flex flex-col md:flex-row space-x-2"},D={key:0,class:"text-gray-600"},E={key:1},O={key:2,class:"text-gray-600"},T=["onSubmit"],U={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"sm:col-span-6"},F={class:"sm:col-span-6"},P={class:"sm:col-span-6"},z=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Category Group ",-1),A={key:0,class:"text-sm text-red-600"},H={class:"sm:col-span-6"},I={class:"flex space-x-1 mt-5 justify-end"},J=o("span",null," Back ",-1),K=o("span",null," Save ",-1),te={__name:"Form",props:{category:Object,categoryGroups:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const a=u,e=g(n(_())),p=g([]);V(()=>{p.value=a.categoryGroups.data,e.value=a.category?n(a.category):n(_()),a.type==="create"&&(e.value.category_group_id=e.value.category_group_id?e.value.category_group_id:"")});function _(){return{name:"",desc:"",category_group_id:""}}function b(){e.value.clearErrors(),a.type==="create"&&e.value.transform(s=>({...s,category_group_id:s.category_group_id.id})).post("/categories/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.transform(s=>({...s,category_group_id:s.category_group_id.id})).post("/categories/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(s,t)=>(i(),C(k,{to:"body"},[r(B,{open:u.showModal,onModalClose:t[4]||(t[4]=l=>s.$emit("modalClose"))},{header:d(()=>[o("div",G,[a.category?(i(),c("span",D," Editing ")):f("",!0),a.category?(i(),c("span",E,y(a.category.name),1)):(i(),c("span",O," Create New Category "))])]),default:d(()=>[o("form",{onSubmit:w(b,["prevent"]),id:"submit"},[o("div",U,[o("div",q,[r(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:d(()=>[v(" Name ")]),_:1},8,["modelValue","error"])]),o("div",F,[r($,{modelValue:e.value.desc,"onUpdate:modelValue":t[1]||(t[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:d(()=>[v(" Desc ")]),_:1},8,["modelValue","error"])]),o("div",P,[z,r(M,{modelValue:e.value.category_group_id,"onUpdate:modelValue":t[2]||(t[2]=l=>e.value.category_group_id=l),options:p.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.category_group_id?(i(),c("div",A,y(e.value.errors.category_group_id),1)):f("",!0)])]),o("div",H,[o("div",I,[r(h,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[3]||(t[3]=l=>s.$emit("modalClose")),form:"submit"},{default:d(()=>[r(x(j),{class:"w-4 h-4"}),J]),_:1}),r(h,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[r(x(N),{class:"w-4 h-4"}),K]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{te as default};
