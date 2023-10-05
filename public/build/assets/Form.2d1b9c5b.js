import{g,T as n,h as V,c as C,a as s,w as i,p as k,o as d,b as o,f as c,l as f,t as y,d as v,u as x,e as w}from"./app.a5ba100b.js";import{_ as h}from"./Button.b17e3b5e.js";import{_ as S}from"./FormInput.52d22637.js";import{_ as $}from"./FormTextarea.9b2bb928.js";import{_ as B}from"./Modal.d8733df9.js";import{_ as N}from"./MultiSelect.52fedbb8.js";import{r as M}from"./ArrowUturnLeftIcon.f641cb83.js";import{r as j}from"./CheckCircleIcon.82c6f758.js";import"./open-closed.34e7965e.js";import"./disposables.d73465c7.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.562fa679.js";const G={class:"flex flex-col md:flex-row space-x-2"},D={key:0,class:"text-gray-600"},E={key:1},O={key:2,class:"text-gray-600"},T=["onSubmit"],U={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},P={class:"sm:col-span-6"},z=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Category Group ",-1),A={key:0,class:"text-sm text-red-600"},H={class:"sm:col-span-6"},I={class:"flex space-x-1 mt-5 justify-end"},J=o("span",null," Back ",-1),K=o("span",null," Save ",-1),re={__name:"Form",props:{category:Object,categoryGroups:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const a=u,e=g(n(_())),p=g([]);V(()=>{p.value=a.categoryGroups.data,e.value=a.category?n(a.category):n(_()),a.type==="create"&&(e.value.category_group_id=e.value.category_group_id?e.value.category_group_id:"")});function _(){return{name:"",desc:"",category_group_id:""}}function b(){e.value.clearErrors(),a.type==="create"&&e.value.transform(r=>({...r,category_group_id:r.category_group_id.id})).post("/categories/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.transform(r=>({...r,category_group_id:r.category_group_id.id})).post("/categories/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(r,t)=>(d(),C(k,{to:"body"},[s(B,{open:u.showModal,onModalClose:t[4]||(t[4]=l=>r.$emit("modalClose"))},{header:i(()=>[o("div",G,[a.category?(d(),c("span",D," Editing ")):f("",!0),a.category?(d(),c("span",E,y(a.category.name),1)):(d(),c("span",O," Create New Category "))])]),default:i(()=>[o("form",{onSubmit:w(b,["prevent"]),id:"submit"},[o("div",U,[o("div",F,[s(S,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:i(()=>[v(" Name ")]),_:1},8,["modelValue","error"])]),o("div",q,[s($,{modelValue:e.value.desc,"onUpdate:modelValue":t[1]||(t[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:i(()=>[v(" Desc ")]),_:1},8,["modelValue","error"])]),o("div",P,[z,s(N,{modelValue:e.value.category_group_id,"onUpdate:modelValue":t[2]||(t[2]=l=>e.value.category_group_id=l),options:p.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.category_group_id?(d(),c("div",A,y(e.value.errors.category_group_id),1)):f("",!0)])]),o("div",H,[o("div",I,[s(h,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[3]||(t[3]=l=>r.$emit("modalClose")),form:"submit"},{default:i(()=>[s(x(M),{class:"w-4 h-4"}),J]),_:1}),s(h,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:i(()=>[s(x(j),{class:"w-4 h-4"}),K]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{re as default};
