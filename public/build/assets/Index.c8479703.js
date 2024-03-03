import{_ as M}from"./Authenticated.4931123b.js";import{_ as v}from"./Button.c220b9da.js";import{_ as L}from"./DatePicker.9e961d77.js";import{_ as U}from"./Paginator.f7889a65.js";import{_ as F,r as N}from"./SearchInput.92a05e09.js";import{_ as $}from"./MultiSelect.1ef9ae33.js";import{g as b,U as D,Q as I,h as A,f as u,a as t,u as c,w as a,F as h,o as i,Z as E,b as e,i as S,d as n,t as d,k as P,l as w,c as R,O as j}from"./app.6c1fd100.js";import{_ as f,a as p}from"./TableData.a3922651.js";import{r as T}from"./PlusIcon.cbe3a136.js";import{r as K}from"./BackspaceIcon.89c108e0.js";import{r as Q}from"./PencilSquareIcon.76a321f7.js";import"./keyboard.a01f6322.js";import"./use-resolve-button-type.0de40f2b.js";import"./RectangleStackIcon.aa1824e2.js";import"./main.0dda3f8c.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.200ceaf2.js";const Z=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Campaign ",-1),q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},G={class:"flex justify-end"},H=e("span",null," Create ",-1),J={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},W=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),X={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ee={class:"mt-3"},te={class:"flex space-x-1"},ae=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),se={class:"flex flex-col space-y-2"},oe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},re=e("span",null,"Showing",-1),ne={class:"font-medium"},de=e("span",null,"to",-1),ie={class:"font-medium"},ue=e("span",null,"of",-1),me={class:"font-medium"},ce=e("span",null,"results",-1),fe={class:"mt-6 flex flex-col"},pe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},_e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ge={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ve={class:"bg-gray-100"},he={class:"divide-x divide-gray-200"},xe={class:"bg-white"},ye={class:"divide-y divide-gray-200"},be={class:"flex flex-col py-1 px-3 space-x-2"},Pe={class:"text-blue-700"},we={class:"divide-y divide-gray-200 pl-3"},Ve={class:"flex py-1 px-3 space-x-2"},Ce={key:0},ke={class:"flex justify-center space-x-1"},Oe=e("span",null," Edit ",-1),Le={key:0},$e=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),De=[$e],qe={__name:"Index",props:{deliveryPlatformCampaigns:Object,deliveryPlatformOperatorOptions:Object,deliveryPlatformOrderStatusOptions:Object,totals:Object},setup(o){const Y=o,s=b({order_id:"",short_order_id:"",vend_code:"",date_from:D().format("YYYY-MM-DD"),date_to:D().format("YYYY-MM-DD"),delivery_platform_operator_id:"",has_complaint:"all",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),x=b([]);I().props.auth.operatorCountry;const y=b([]);I().props.auth.permissions,A(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=y.value[0],x.value=[{id:"all",name:"All"},...Y.deliveryPlatformOperatorOptions.data.map(g=>({id:g.id,name:g.deliveryPlatform.name+" ("+g.type+")"}))],s.value.delivery_platform_operator_id=x.value[0]});function V(){j.get("/delivery-platform-orders",{...s.value,delivery_platform_operator_id:s.value.delivery_platform_operator_id.id,status:s.value.status.id,has_complaint:s.value.has_complaint.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function B(){j.get("/delivery-platform-orders")}return(g,r)=>(i(),u(h,null,[t(c(E),{title:"Delivery Campaign"}),t(M,null,{header:a(()=>[Z]),default:a(()=>{var C,k;return[e("div",q,[e("div",z,[e("div",G,[t(c(S),{href:"/delivery-platform-campaigns/create"},{default:a(()=>[t(v,{type:"button",class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:a(()=>[t(c(T),{class:"h-4 w-4","aria-hidden":"true"}),H]),_:1})]),_:1})]),e("div",J,[t(F,{placeholderStr:"Vend ID",modelValue:s.value.vend_code,"onUpdate:modelValue":r[0]||(r[0]=l=>s.value.vend_code=l)},{default:a(()=>[n(" Vend ID ")]),_:1},8,["modelValue"]),t(L,{modelValue:s.value.date_from,"onUpdate:modelValue":r[1]||(r[1]=l=>s.value.date_from=l)},{default:a(()=>[n(" From ")]),_:1},8,["modelValue"]),t(L,{modelValue:s.value.date_to,"onUpdate:modelValue":r[2]||(r[2]=l=>s.value.date_to=l),minDate:s.value.date_from},{default:a(()=>[n(" To ")]),_:1},8,["modelValue","minDate"]),e("div",null,[W,t($,{modelValue:s.value.delivery_platform_operator_id,"onUpdate:modelValue":r[3]||(r[3]=l=>s.value.delivery_platform_operator_id=l),options:x.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",X,[e("div",ee,[e("div",te,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[4]||(r[4]=l=>V())},{default:a(()=>[t(c(N),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[5]||(r[5]=l=>B())},{default:a(()=>[t(c(K),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",se,[e("p",oe,[re,e("span",ne,d((C=o.deliveryPlatformCampaigns.meta.from)!=null?C:0),1),de,e("span",ie,d((k=o.deliveryPlatformCampaigns.meta.to)!=null?k:0),1),ue,e("span",me,d(o.deliveryPlatformCampaigns.meta.total),1),ce]),t($,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":r[6]||(r[6]=l=>s.value.numberPerPage=l),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:V},null,8,["modelValue","options"])])])]),e("div",fe,[e("div",pe,[e("div",_e,[e("table",ge,[e("thead",ve,[e("tr",he,[t(f,null,{default:a(()=>[n(" # ")]),_:1}),t(f,null,{default:a(()=>[n(" Platform ")]),_:1}),t(f,null,{default:a(()=>[n(" Name ")]),_:1}),t(f,null,{default:a(()=>[n(" Status ")]),_:1}),t(f,null,{default:a(()=>[n(" Campaign Item(s) ")]),_:1}),t(f,null,{default:a(()=>[n(" Product Mapping ")]),_:1}),t(f)])]),e("tbody",xe,[(i(!0),u(h,null,P(o.deliveryPlatformCampaigns.data,(l,m)=>(i(),u("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(o.deliveryPlatformCampaigns.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(l.deliveryPlatformOperator.deliveryPlatform.name)+" ("+d(l.deliveryPlatformOperator.type)+") ",1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(l.is_active?"Active":"Inactive"),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-left"},{default:a(()=>[e("ul",ye,[(i(!0),u(h,null,P(l.deliveryPlatformCampaignItems,O=>(i(),u("li",be,[e("span",Pe,d(O.settings_label),1),e("ul",we,[(i(!0),u(h,null,P(O.items_json,_=>(i(),u("li",Ve,[_&&"full_name"in _?(i(),u("span",Ce,d(_.full_name?_.full_name:_.name),1)):w("",!0)]))),256))])]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(l.deliveryProductMapping.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[e("div",ke,[t(c(S),{href:"/delivery-platform-campaigns/"+l.id+"/edit"},{default:a(()=>[t(v,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"},{default:a(()=>[t(c(Q),{class:"w-4 h-4"}),Oe]),_:1})]),_:2},1032,["href"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.deliveryPlatformCampaigns.data.length?w("",!0):(i(),u("tr",Le,De))])]),o.deliveryPlatformCampaigns.data.length?(i(),R(U,{key:0,links:o.deliveryPlatformCampaigns.links,meta:o.deliveryPlatformCampaigns.meta},null,8,["links","meta"])):w("",!0)])])])])]}),_:1})],64))}};export{qe as default};
