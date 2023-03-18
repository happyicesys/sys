import{o as a,g as l,d as s,i as m,u as h,l as j,j as B,c as f,a as i,w as c,p as u,T as N,t as _,b as v,e as k,n as U,f as b}from"./app.146697fc.js";import{_ as y}from"./Button.f622c4c1.js";import z from"./Uom.eafa2022.js";import{_ as C}from"./FormInput.4d639907.js";import{_ as D}from"./FormTextarea.8719873f.js";import{_ as E}from"./MultiSelect.2e41422a.js";import{_ as F}from"./Modal.3c9c22b5.js";import{r as P}from"./RectangleStackIcon.312a3d54.js";import{r as T}from"./ArrowUturnLeftIcon.db134604.js";import{r as q}from"./CheckCircleIcon.d84c6873.js";import"./open-closed.00420709.js";function G(t,p){return a(),l("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[s("path",{"fill-rule":"evenodd",d:"M2 4.75C2 3.784 2.784 3 3.75 3h4.836c.464 0 .909.184 1.237.513l1.414 1.414a.25.25 0 00.177.073h4.836c.966 0 1.75.784 1.75 1.75v8.5A1.75 1.75 0 0116.25 17H3.75A1.75 1.75 0 012 15.25V4.75zm10.25 7a.75.75 0 000-1.5h-4.5a.75.75 0 000 1.5h4.5z","clip-rule":"evenodd"})])}function H(t,p){return a(),l("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[s("path",{"fill-rule":"evenodd",d:"M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-8.5A1.75 1.75 0 0016.25 5h-4.836a.25.25 0 01-.177-.073L9.823 3.513A1.75 1.75 0 008.586 3H3.75zM10 8a.75.75 0 01.75.75v1.5h1.5a.75.75 0 010 1.5h-1.5v1.5a.75.75 0 01-1.5 0v-1.5h-1.5a.75.75 0 010-1.5h1.5v-1.5A.75.75 0 0110 8z","clip-rule":"evenodd"})])}const I={class:"flex flex-col md:flex-row space-x-2"},L={key:0,class:"text-gray-600"},R={key:1},J={key:2,class:"text-gray-600"},K=["onSubmit"],Q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},W={class:"sm:col-span-6 pb-3"},X={class:"mt-1 flex flex-col md:flex-row space-y-2 md:space-y-0 items-center"},Y={class:"h-28 w-28 overflow-hidden rounded-full bg-gray-100"},Z=["src"],ee=["value"],se={key:0,class:"text-sm text-red-600"},te={class:"sm:col-span-2"},oe=b(" Code "),ae={class:"sm:col-span-4"},re=b(" Name "),le={class:"sm:col-span-6"},de=b(" Desc "),ie={class:"col-span-12 sm:col-span-6"},ne=s("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),ue={key:0,class:"text-sm text-red-600"},ce={class:"sm:col-span-6"},me={class:"flex space-x-1 mt-5 pt-5 justify-end"},pe=s("span",null," Back ",-1),ve={key:0,class:"flex space-x-1 items-center"},fe=s("span",null," Deactivate ",-1),_e={key:1,class:"flex space-x-1 items-center"},ge=s("span",null," Activate ",-1),he=s("span",null," Save ",-1),Ae={__name:"Form",props:{categories:Object,categoryGroups:Object,product:Object,uoms:Object,type:String,showModal:Boolean,operatorOptions:Object,permissions:[Array,Object]},emits:["modalClose"],setup(t,{emit:p}){const n=t,V=m([]),$=m([]),g=m(!1),M=m([]),e=m(h(w())),x=m([]);j().props.value.auth.operatorRole,B(()=>{e.value=n.product?h(n.product):h(w()),V.value=n.categories.data.map(o=>({id:o.id,name:o.name})),$.value=n.categoryGroups.data.map(o=>({id:o.id,name:o.name})),M.value=n.uoms.data.map(o=>({id:o.id,name:o.name})),x.value=n.operatorOptions.slice(1)});function w(){return{code:"",desc:"",name:"",thumbnail:"",is_inventory:1,is_commission:"",is_supermarket_fee:"",category_id:"",category_group_id:"",operator_id:""}}function O(){e.value.clearErrors(),n.type==="create"&&e.value.transform(o=>({...o,operator_id:o.operator_id.id})).post("/products/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),n.type==="update"&&e.value.transform(o=>({...o,operator_id:o.operator_id.id})).post("/products/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}function S(){e.value.post("/products/"+e.value.id+"/toggle-activate-deactivate",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}function A(){g.value=!1}return(o,r)=>(a(),f(N,{to:"body"},[i(F,{open:t.showModal,onModalClose:r[6]||(r[6]=d=>o.$emit("modalClose"))},{header:c(()=>[s("div",I,[t.product?(a(),l("span",L," Editing ")):u("",!0),n.product?(a(),l("span",R,_(t.product.name),1)):(a(),l("span",J," Create New Product "))])]),default:c(()=>[s("form",{onSubmit:k(O,["prevent"]),id:"submit"},[s("div",Q,[s("div",W,[s("div",X,[s("span",Y,[t.product&&t.product.thumbnail?(a(),l("img",{key:0,class:"h-28 w-28 rounded-full border",src:t.product.thumbnail.full_url,alt:""},null,8,Z)):u("",!0),i(v(P),{class:"h-28 w-28 text-gray-300"})]),t.permissions.includes("update products")?(a(),l("input",{key:0,type:"file",onInput:r[0]||(r[0]=d=>e.value.thumbnail=d.target.files[0]),class:"ml-5 rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},null,32)):u("",!0),e.value.progress?(a(),l("progress",{key:1,value:e.value.progress.percentage,max:"100"},_(e.value.progress.percentage)+"% ",9,ee)):u("",!0)]),e.value.errors.thumbnail?(a(),l("div",se,_(e.value.errors.thumbnail),1)):u("",!0)]),s("div",te,[i(C,{modelValue:e.value.code,"onUpdate:modelValue":r[1]||(r[1]=d=>e.value.code=d),error:e.value.errors.code,disabled:!t.permissions.includes("update products"),required:"true"},{default:c(()=>[oe]),_:1},8,["modelValue","error","disabled"])]),s("div",ae,[i(C,{modelValue:e.value.name,"onUpdate:modelValue":r[2]||(r[2]=d=>e.value.name=d),error:e.value.errors.name,disabled:!t.permissions.includes("update products"),required:"true"},{default:c(()=>[re]),_:1},8,["modelValue","error","disabled"])]),s("div",le,[i(D,{modelValue:e.value.desc,"onUpdate:modelValue":r[3]||(r[3]=d=>e.value.desc=d),disabled:!t.permissions.includes("update products"),error:e.value.errors.desc},{default:c(()=>[de]),_:1},8,["modelValue","disabled","error"])]),s("div",ie,[ne,i(E,{modelValue:e.value.operator_id,"onUpdate:modelValue":r[4]||(r[4]=d=>e.value.operator_id=d),options:x.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"top",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.operator_id?(a(),l("div",ue,_(e.value.errors.operator_id),1)):u("",!0)])]),s("div",ce,[s("div",me,[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:r[5]||(r[5]=k(d=>o.$emit("modalClose"),["prevent"])),form:"submit"},{default:c(()=>[i(v(T),{class:"w-4 h-4"}),pe]),_:1}),e.value.id&&t.permissions.includes("update products")?(a(),f(y,{key:0,type:"button",onClick:S,class:U(["text-white",[e.value.is_active?"bg-red-500 hover:bg-red-600":"bg-green-500 hover:bg-green-600"]])},{default:c(()=>[s("div",null,[e.value.is_active?(a(),l("span",ve,[i(v(G),{class:"w-4 h-4"}),fe])):(a(),l("span",_e,[i(v(H),{class:"w-4 h-4"}),ge]))])]),_:1},8,["class"])):u("",!0),t.permissions.includes("update products")?(a(),f(y,{key:1,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:c(()=>[i(v(q),{class:"w-4 h-4"}),he]),_:1})):u("",!0)])])],40,K)]),_:1},8,["open"]),g.value?(a(),f(z,{key:0,product:t.product,uoms:t.uoms,showModal:g.value,onModalClose:A},null,8,["product","uoms","showModal"])):u("",!0)]))}};export{Ae as default};
