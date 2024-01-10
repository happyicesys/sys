import{_ as F}from"./Authenticated.9c7a946e.js";import{_ as g}from"./Button.d49610f5.js";import{_ as k}from"./DatePicker.4d893a6a.js";import{_ as M}from"./Paginator.af1e7913.js";import{_ as U,r as T}from"./SearchInput.0fd1574f.js";import{_ as $}from"./MultiSelect.dc61c537.js";import{g as P,U as B,Q as D,h as A,f as c,a as t,u as f,w as a,F as v,o as u,Z as E,b as e,i as I,d as n,t as d,k as w,l as S,c as R,O as j}from"./app.8f9fd870.js";import{_ as p,a as m}from"./TableData.f31dca66.js";import{_ as K}from"./TableHeadSort.2cee9b0a.js";import{r as Q}from"./PlusIcon.663b1017.js";import{r as Z}from"./BackspaceIcon.38303ee3.js";import{r as q}from"./PencilSquareIcon.65220a2d.js";import"./keyboard.339bd933.js";import"./use-resolve-button-type.fcc9a671.js";import"./RectangleStackIcon.5f55e086.js";import"./main.b04df660.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.7c3f3866.js";const z=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Campaign ",-1),G={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},H={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},J={class:"flex justify-end"},W=e("span",null," Create ",-1),X={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ee=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),te={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},le={class:"flex space-x-1"},se=e("span",null," Search ",-1),oe=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ne={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ie={class:"font-medium"},ue=e("span",null,"to",-1),me={class:"font-medium"},ce=e("span",null,"of",-1),fe={class:"font-medium"},pe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},ge={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},xe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},he={class:"bg-gray-100"},ye={class:"divide-x divide-gray-200"},be={class:"bg-white"},Pe={class:"divide-y divide-gray-200"},we={class:"flex flex-col py-1 px-3 space-x-2"},Ve={class:"text-blue-700"},Le={class:"divide-y divide-gray-200 pl-3"},Ce={class:"flex py-1 px-3 space-x-2"},Oe={class:"flex justify-center space-x-1"},ke=e("span",null," Edit ",-1),$e={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),De=[Be],Ge={__name:"Index",props:{deliveryPlatformCampaigns:Object,deliveryPlatformOperatorOptions:Object,deliveryPlatformOrderStatusOptions:Object,totals:Object},setup(o){const N=o,l=P({order_id:"",short_order_id:"",vend_code:"",date_from:B().format("YYYY-MM-DD"),date_to:B().format("YYYY-MM-DD"),delivery_platform_operator_id:"",has_complaint:"all",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),x=P([]);D().props.auth.operatorCountry;const h=P([]);D().props.auth.permissions,A(()=>{h.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=h.value[0],x.value=[{id:"all",name:"All"},...N.deliveryPlatformOperatorOptions.data.map(_=>({id:_.id,name:_.deliveryPlatform.name+" ("+_.type+")"}))],l.value.delivery_platform_operator_id=x.value[0]});function y(){j.get("/delivery-platform-orders",{...l.value,delivery_platform_operator_id:l.value.delivery_platform_operator_id.id,status:l.value.status.id,has_complaint:l.value.has_complaint.id,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function Y(){j.get("/delivery-platform-orders")}function V(_){l.value.sortKey=_,l.value.sortBy=!l.value.sortBy,y()}return(_,r)=>(u(),c(v,null,[t(f(E),{title:"Delivery Platform"}),t(F,null,{header:a(()=>[z]),default:a(()=>{var L,C;return[e("div",G,[e("div",H,[e("div",J,[t(f(I),{href:"/delivery-platform-campaigns/create"},{default:a(()=>[t(g,{type:"button",class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:a(()=>[t(f(Q),{class:"h-4 w-4","aria-hidden":"true"}),W]),_:1})]),_:1})]),e("div",X,[t(U,{placeholderStr:"Vend ID",modelValue:l.value.vend_code,"onUpdate:modelValue":r[0]||(r[0]=s=>l.value.vend_code=s)},{default:a(()=>[n(" Vend ID ")]),_:1},8,["modelValue"]),t(k,{modelValue:l.value.date_from,"onUpdate:modelValue":r[1]||(r[1]=s=>l.value.date_from=s)},{default:a(()=>[n(" From ")]),_:1},8,["modelValue"]),t(k,{modelValue:l.value.date_to,"onUpdate:modelValue":r[2]||(r[2]=s=>l.value.date_to=s),minDate:l.value.date_from},{default:a(()=>[n(" To ")]),_:1},8,["modelValue","minDate"]),e("div",null,[ee,t($,{modelValue:l.value.delivery_platform_operator_id,"onUpdate:modelValue":r[3]||(r[3]=s=>l.value.delivery_platform_operator_id=s),options:x.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",te,[e("div",ae,[e("div",le,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[4]||(r[4]=s=>y())},{default:a(()=>[t(f(T),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1}),t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[5]||(r[5]=s=>Y())},{default:a(()=>[t(f(Z),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1})])]),e("div",re,[e("p",ne,[de,e("span",ie,d((L=o.deliveryPlatformCampaigns.meta.from)!=null?L:0),1),ue,e("span",me,d((C=o.deliveryPlatformCampaigns.meta.to)!=null?C:0),1),ce,e("span",fe,d(o.deliveryPlatformCampaigns.meta.total),1),pe]),t($,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":r[6]||(r[6]=s=>l.value.numberPerPage=s),options:h.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",ge,[e("div",ve,[e("table",xe,[e("thead",he,[e("tr",ye,[t(p,null,{default:a(()=>[n(" # ")]),_:1}),t(p,null,{default:a(()=>[n(" Platform ")]),_:1}),t(K,{modelName:"datetime_from",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:r[7]||(r[7]=s=>V("datetime_from"))},{default:a(()=>[n(" Date From ")]),_:1},8,["sortKey","sortBy"]),t(K,{modelName:"datetime_to",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:r[8]||(r[8]=s=>V("datetime_to"))},{default:a(()=>[n(" Date To ")]),_:1},8,["sortKey","sortBy"]),t(p,null,{default:a(()=>[n(" Name ")]),_:1}),t(p,null,{default:a(()=>[n(" Status ")]),_:1}),t(p,null,{default:a(()=>[n(" Campaign Item(s) ")]),_:1}),t(p,null,{default:a(()=>[n(" Product Mapping ")]),_:1}),t(p)])]),e("tbody",be,[(u(!0),c(v,null,w(o.deliveryPlatformCampaigns.data,(s,i)=>(u(),c("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(o.deliveryPlatformCampaigns.meta.from+i),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.deliveryPlatformOperator.deliveryPlatform.name)+" ("+d(s.deliveryPlatformOperator.type)+") ",1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.datetime_from),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.datetime_to),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.is_active?"Active":"Inactive"),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-left"},{default:a(()=>[e("ul",Pe,[(u(!0),c(v,null,w(s.deliveryPlatformCampaignItems,O=>(u(),c("li",we,[e("span",Ve,d(O.settings_label),1),e("ul",Le,[(u(!0),c(v,null,w(O.items_json,b=>(u(),c("li",Ce,[e("span",null,d(b.full_name?b.full_name:b.name),1)]))),256))])]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[n(d(s.deliveryProductMapping.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:i,totalLength:o.deliveryPlatformCampaigns.length,inputClass:"text-center"},{default:a(()=>[e("div",Oe,[t(f(I),{href:"/delivery-platform-campaigns/"+s.id+"/edit"},{default:a(()=>[t(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"},{default:a(()=>[t(f(q),{class:"w-4 h-4"}),ke]),_:1})]),_:2},1032,["href"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.deliveryPlatformCampaigns.data.length?S("",!0):(u(),c("tr",$e,De))])]),o.deliveryPlatformCampaigns.data.length?(u(),R(M,{key:0,links:o.deliveryPlatformCampaigns.links,meta:o.deliveryPlatformCampaigns.meta},null,8,["links","meta"])):S("",!0)])])])])]}),_:1})],64))}};export{Ge as default};