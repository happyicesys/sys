import{g as b,T as _,h as w,f as d,a as r,u as n,w as l,F as k,o as c,Z as V,b as e,d as f,t as i,l as p,e as O,n as P,i as z}from"./app.312f158b.js";import{_ as j}from"./Authenticated.89206990.js";import{_ as x}from"./Button.99e71920.js";import{_ as S}from"./FormInput.42d3643d.js";import{_ as D}from"./FormTextarea.cc742ce7.js";import{_ as g}from"./MultiSelect.429cd89e.js";import{r as C}from"./ArrowUturnLeftIcon.f792b7b8.js";import{r as B}from"./CheckCircleIcon.e7133973.js";import"./open-closed.b4875b43.js";import"./use-resolve-button-type.40ddd40d.js";import"./RectangleStackIcon.88fe34ba.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.b9a09d3c.js";const $={class:"font-semibold text-xl text-gray-800 leading-tight"},T={key:0},E={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},F={class:"mt-6 flex flex-col"},N={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},U={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},M=["onSubmit"],G={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},I={class:"sm:col-span-3"},R={class:"mt-1"},A={class:"flex flex-col"},Z={class:"font-semibold"},q={class:"sm:col-span-3"},H=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Order ID ",-1),J={class:"mt-1"},K=["value"],L={class:"sm:col-span-3"},Q=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Short Order ID ",-1),W={class:"mt-1"},X=["value"],Y={class:"sm:col-span-6"},ee=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),te={class:"mt-1"},se=["value"],oe={class:"sm:col-span-6"},re=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Order Datetime ",-1),ae={class:"mt-1"},le=["value"],ie={class:"sm:col-span-3"},de=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),ne={key:0,class:"text-sm text-red-600"},ce={class:"sm:col-span-3"},me=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Timezone ",-1),ue={key:0,class:"text-sm text-red-600"},_e={class:"sm:col-span-4"},fe=e("span",{class:"text-[9px]"}," (For Gross Margin Calculation) ",-1),pe={class:"sm:col-span-6"},ve={class:"sm:col-span-6"},xe={class:"flex space-x-1 mt-5 justify-end"},ge=e("span",null," Back ",-1),ye=e("span",null," Save ",-1),Be={__name:"Edit",props:{deliveryPlatformOrder:Object},setup(m){const u=m,t=b(_(v()));w(()=>{t.value=u.deliveryPlatformOrder?_(u.deliveryPlatformOrder.data):_(v())});function v(){return{id:"",order_id:"",short_order_id:"",deliveryPlatform:{name:""},operator_id:"",order_created_at:""}}function y(o){let s="";switch(o){case 1:case 2:s="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:s="bg-yellow-400 text-gray-800";break;case 6:s="bg-green-400 text-white-800";break;case 98:case 99:s="bg-red-400 text-white-800";break}return s}function h(){t.value.clearErrors(),u.type==="update"&&t.value.transform(o=>({...o,timezone:o.timezone?o.timezone.name:null,country_id:o.country_id?o.country_id.id:null})).post("/operators/"+t.value.id+"/update",{preserveState:!0,replace:!0})}return(o,s)=>(c(),d(k,null,[r(n(V),{title:"Delivery Platform Order"}),r(j,null,{header:l(()=>[e("h2",$,[f(" Edit Delivery Platform Order "),o.type=="update"?(c(),d("span",T,i(t.value.order_id)+" "+i(t.value.short_order_id),1)):p("",!0)])]),default:l(()=>[e("div",E,[e("div",F,[e("div",N,[e("div",U,[e("form",{onSubmit:O(h,["prevent"]),id:"submit"},[e("div",G,[e("div",I,[e("div",R,[e("div",{class:P(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",y(m.deliveryPlatformOrder.status)])},[e("div",A,[e("span",Z,i(m.deliveryPlatformOrder.status_name),1)])],2)])]),e("div",q,[H,e("div",J,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.order_id},null,8,K)])]),e("div",L,[Q,e("div",W,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.short_order_id},null,8,X)])]),e("div",Y,[ee,e("div",te,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.deliveryPlatform.name},null,8,se)])]),e("div",oe,[re,e("div",ae,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.order_created_at},null,8,le)])]),e("div",ie,[de,r(g,{modelValue:t.value.country_id,"onUpdate:modelValue":s[0]||(s[0]=a=>t.value.country_id=a),options:o.countryOptions,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.country_id?(c(),d("div",ne,i(t.value.errors.country_id),1)):p("",!0)]),e("div",ce,[me,r(g,{modelValue:t.value.timezone,"onUpdate:modelValue":s[1]||(s[1]=a=>t.value.timezone=a),options:o.timezoneOptions,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.timezone?(c(),d("div",ue,i(t.value.errors.timezone),1)):p("",!0)]),e("div",_e,[r(S,{modelValue:t.value.gst_vat_rate,"onUpdate:modelValue":s[2]||(s[2]=a=>t.value.gst_vat_rate=a),error:t.value.errors.gst_vat_rate},{default:l(()=>[f(" GST or VAT Rate (%) "),fe]),_:1},8,["modelValue","error"])]),e("div",pe,[r(D,{modelValue:t.value.remarks,"onUpdate:modelValue":s[3]||(s[3]=a=>t.value.remarks=a),error:t.value.errors.remarks},{default:l(()=>[f(" Remarks ")]),_:1},8,["modelValue","error"])]),e("div",ve,[e("div",xe,[r(n(z),{href:"/operators"},{default:l(()=>[r(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:l(()=>[r(n(C),{class:"w-4 h-4"}),ge]),_:1})]),_:1}),r(x,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[r(n(B),{class:"w-4 h-4"}),ye]),_:1})])])])],40,M)])])])])]),_:1})],64))}};export{Be as default};
