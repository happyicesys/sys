import{g as v,T as f,Q as V,h as P,f as p,a as o,u as i,w as l,F as S,o as d,Z as w,b as r,e as B,d as n,t as y,l as m,i as C,c as M,O as N}from"./app.6c1fd100.js";import{_ as $}from"./Authenticated.4931123b.js";import{_ as g}from"./Button.c220b9da.js";import"./main.0dda3f8c.js";import{_ as j}from"./FormInput.80b139a0.js";import{_ as D}from"./FormTextarea.672081a2.js";import{_ as x}from"./MultiSelect.1ef9ae33.js";import{r as U}from"./ArrowUturnLeftIcon.0e5d2ed7.js";import{r as F}from"./CheckCircleIcon.d3a38b3a.js";import"./keyboard.a01f6322.js";import"./use-resolve-button-type.0de40f2b.js";import"./RectangleStackIcon.aa1824e2.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.200ceaf2.js";const E=r("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Create New Campaign ",-1),T={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},q={class:"mt-6 flex flex-col"},Q={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},R={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},Z={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},z={class:"sm:col-span-6"},A={class:"sm:col-span-6"},G=r("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[n(" Delivery Product Mapping "),r("span",{class:"text-red-500"}," * ")],-1),H={key:0,class:"text-sm text-red-600"},I={key:0,class:"sm:col-span-6"},J=r("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[n(" Platform "),r("span",{class:"text-red-500"}," * ")],-1),K={key:0,class:"text-sm text-red-600"},L={class:"sm:col-span-6"},W={class:"sm:col-span-6"},X={class:"flex space-x-1 mt-5 justify-end"},Y=r("span",null," Back ",-1),ee=r("span",null," Save ",-1),ue={__name:"Create",props:{deliveryPlatformOperatorOptions:Object,deliveryProductMappingOptions:Object},setup(h){const _=h,c=v([]),e=v(f(u())),b=V().props.auth.permissions;P(()=>{e.value=f(u()),c.value=[..._.deliveryPlatformOperatorOptions.data.map(a=>({id:a.id,name:a.deliveryPlatform.name+" ("+a.type+")"}))]});function u(){return{name:"",delivery_product_mapping_id:"",delivery_platform_operator_id:"",remarks:""}}function k(){N.reload({only:["deliveryPlatformOperatorOptions"],data:{delivery_product_mapping_id:e.value.delivery_product_mapping_id.id},replace:!0,preserveState:!0,onSuccess:a=>{c.value=[..._.deliveryPlatformOperatorOptions.data.map(t=>({id:t.id,name:t.deliveryPlatform.name+" ("+t.type+")"}))]}})}function O(){e.value.clearErrors(),e.value.transform(a=>({...a,delivery_product_mapping_id:a.delivery_product_mapping_id.id,delivery_platform_operator_id:a.delivery_platform_operator_id.id})).post("/delivery-platform-campaigns/store",{preserveState:!0,replace:!0})}return(a,t)=>(d(),p(S,null,[o(i(w),{title:"Campaign"}),o($,null,{header:l(()=>[E]),default:l(()=>[r("div",T,[r("div",q,[r("div",Q,[r("div",R,[r("form",{onSubmit:B(O,["prevent"]),id:"submit"},[r("div",Z,[r("div",z,[o(j,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=s=>e.value.name=s),error:e.value.errors.name,required:"true"},{default:l(()=>[n(" Name ")]),_:1},8,["modelValue","error"])]),r("div",A,[G,o(x,{modelValue:e.value.delivery_product_mapping_id,"onUpdate:modelValue":t[1]||(t[1]=s=>e.value.delivery_product_mapping_id=s),options:_.deliveryProductMappingOptions.data,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"]),e.value.errors.delivery_product_mapping_id?(d(),p("div",H,y(e.value.errors.delivery_product_mapping_id),1)):m("",!0)]),e.value.delivery_product_mapping_id?(d(),p("div",I,[J,o(x,{modelValue:e.value.delivery_platform_operator_id,"onUpdate:modelValue":t[2]||(t[2]=s=>e.value.delivery_platform_operator_id=s),options:c.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.delivery_platform_operator_id?(d(),p("div",K,y(e.value.errors.delivery_platform_operator_id),1)):m("",!0)])):m("",!0),r("div",L,[o(D,{modelValue:e.value.remarks,"onUpdate:modelValue":t[3]||(t[3]=s=>e.value.remarks=s),error:e.value.errors.remarks},{default:l(()=>[n(" Remarks ")]),_:1},8,["modelValue","error"])]),r("div",W,[r("div",X,[o(i(C),{href:"/operators"},{default:l(()=>[o(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:l(()=>[o(i(U),{class:"w-4 h-4"}),Y]),_:1})]),_:1}),i(b).includes("update operators")?(d(),M(g,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[o(i(F),{class:"w-4 h-4"}),ee]),_:1})):m("",!0)])])])],32)])])])])]),_:1})],64))}};export{ue as default};
