import{j as f,g as y,T as g,Q as S,h as w,U as n,f as p,a,u as d,w as l,F as B,o as m,Z as H,b as t,e as $,d as i,t as x,l as u,i as C,c as U,O as j}from"./app.242e5fba.js";import{_ as N}from"./Authenticated.3a544bed.js";import{_ as h}from"./Button.be945d63.js";import{_ as V}from"./DatetimePicker.8340044f.js";import{_ as F}from"./FormInput.15b0f65d.js";import{_ as E}from"./FormTextarea.a74fe052.js";import{_ as b}from"./MultiSelect.fb25c41f.js";import{r as T}from"./ArrowUturnLeftIcon.88830442.js";import{r as q}from"./CheckCircleIcon.a28321ba.js";import"./keyboard.034c1cc1.js";import"./use-resolve-button-type.ce060f77.js";import"./RectangleStackIcon.e3df9db3.js";import"./main.46495f95.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1155e6b8.js";const I=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Create New Campaign ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},R={class:"mt-6 flex flex-col"},Z={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},z={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},A={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},G={class:"sm:col-span-6"},J={class:"sm:col-span-6"},K=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[i(" Delivery Product Mapping "),t("span",{class:"text-red-500"}," * ")],-1),L={key:0,class:"text-sm text-red-600"},W={key:0,class:"sm:col-span-6"},X=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[i(" Platform "),t("span",{class:"text-red-500"}," * ")],-1),ee={key:0,class:"text-sm text-red-600"},te={class:"sm:col-span-3"},re=t("span",{class:"text-red-500"}," * ",-1),oe={class:"sm:col-span-3"},ae=t("span",{class:"text-red-500"}," * ",-1),se={class:"sm:col-span-6"},le={class:"sm:col-span-6"},ie={class:"flex space-x-1 mt-5 justify-end"},de=t("span",null," Back ",-1),me=t("span",null," Save ",-1),ke={__name:"Create",props:{deliveryPlatformOperatorOptions:Object,deliveryProductMappingOptions:Object},setup(D){const _=D,k=f(function(){return n().add(30,"minutes").format("YYYY-MM-DD HH:mm:ss")}),O=f(function(){return n(e.value.datetime_from).add(2,"hours").format("YYYY-MM-DD HH:mm:ss")}),c=y([]),e=y(g(v())),Y=S().props.auth.permissions;w(()=>{e.value=g(v()),e.value.datetime_from=n().add(30,"minutes").format("YYYY-MM-DD HH:mm:ss"),e.value.datetime_to=n(e.value.datetime_from).endOf("month").format("YYYY-MM-DD HH:mm:ss"),c.value=[..._.deliveryPlatformOperatorOptions.data.map(s=>({id:s.id,name:s.deliveryPlatform.name+" ("+s.type+")"}))]});function v(){return{name:"",delivery_product_mapping_id:"",delivery_platform_operator_id:"",datetime_from:"",datetime_to:"",remarks:""}}function M(){j.reload({only:["deliveryPlatformOperatorOptions"],data:{delivery_product_mapping_id:e.value.delivery_product_mapping_id.id},replace:!0,preserveState:!0,onSuccess:s=>{c.value=[..._.deliveryPlatformOperatorOptions.data.map(r=>({id:r.id,name:r.deliveryPlatform.name+" ("+r.type+")"}))]}})}function P(){e.value.clearErrors(),e.value.transform(s=>({...s,delivery_product_mapping_id:s.delivery_product_mapping_id.id,delivery_platform_operator_id:s.delivery_platform_operator_id.id})).post("/delivery-platform-campaigns/store",{preserveState:!0,replace:!0})}return(s,r)=>(m(),p(B,null,[a(d(H),{title:"Campaign"}),a(N,null,{header:l(()=>[I]),default:l(()=>[t("div",Q,[t("div",R,[t("div",Z,[t("div",z,[t("form",{onSubmit:$(P,["prevent"]),id:"submit"},[t("div",A,[t("div",G,[a(F,{modelValue:e.value.name,"onUpdate:modelValue":r[0]||(r[0]=o=>e.value.name=o),error:e.value.errors.name,required:"true"},{default:l(()=>[i(" Name ")]),_:1},8,["modelValue","error"])]),t("div",J,[K,a(b,{modelValue:e.value.delivery_product_mapping_id,"onUpdate:modelValue":r[1]||(r[1]=o=>e.value.delivery_product_mapping_id=o),options:_.deliveryProductMappingOptions.data,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:M},null,8,["modelValue","options"]),e.value.errors.delivery_product_mapping_id?(m(),p("div",L,x(e.value.errors.delivery_product_mapping_id),1)):u("",!0)]),e.value.delivery_product_mapping_id?(m(),p("div",W,[X,a(b,{modelValue:e.value.delivery_platform_operator_id,"onUpdate:modelValue":r[2]||(r[2]=o=>e.value.delivery_platform_operator_id=o),options:c.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.delivery_platform_operator_id?(m(),p("div",ee,x(e.value.errors.delivery_platform_operator_id),1)):u("",!0)])):u("",!0),t("div",te,[a(V,{modelValue:e.value.datetime_from,"onUpdate:modelValue":r[3]||(r[3]=o=>e.value.datetime_from=o),error:e.value.errors.datetime_from,minDate:k.value,onInput:r[4]||(r[4]=o=>s.onDateFromChanged())},{default:l(()=>[i(" Begin Date "),re]),_:1},8,["modelValue","error","minDate"])]),t("div",oe,[a(V,{modelValue:e.value.datetime_to,"onUpdate:modelValue":r[5]||(r[5]=o=>e.value.datetime_to=o),error:e.value.errors.datetime_to,minDate:O.value},{default:l(()=>[i(" End Date "),ae]),_:1},8,["modelValue","error","minDate"])]),t("div",se,[a(E,{modelValue:e.value.remarks,"onUpdate:modelValue":r[6]||(r[6]=o=>e.value.remarks=o),error:e.value.errors.remarks},{default:l(()=>[i(" Remarks ")]),_:1},8,["modelValue","error"])]),t("div",le,[t("div",ie,[a(d(C),{href:"/operators"},{default:l(()=>[a(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:l(()=>[a(d(T),{class:"w-4 h-4"}),de]),_:1})]),_:1}),d(Y).includes("update operators")?(m(),U(h,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[a(d(q),{class:"w-4 h-4"}),me]),_:1})):u("",!0)])])])],32)])])])])]),_:1})],64))}};export{ke as default};
