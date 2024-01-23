import{_ as q}from"./Authenticated.2aece57f.js";import{_ as $}from"./Button.2dd609ee.js";import z from"./ChannelOverview.e7fbac15.js";import{_ as E}from"./Paginator.b95bd080.js";import{_ as D,r as K}from"./SearchInput.c091d82e.js";import{_ as V}from"./MultiSelect.be923325.js";import{_ as p,a as h}from"./TableData.6dbcdb92.js";import{g as c,Q as I,h as Q,f as u,a as l,u as k,w as n,F as w,o as d,Z as T,b as e,d as r,t as m,k as L,l as y,c as j,O as N,n as U}from"./app.be5f36f8.js";import{r as Z}from"./BackspaceIcon.2196c508.js";import"./keyboard.a111d22b.js";import"./use-resolve-button-type.f4da69a8.js";import"./RectangleStackIcon.8844149b.js";import"./FormInput.4baf9a3b.js";import"./Modal.6fb501cb.js";import"./disposables.0e1d279f.js";import"./CheckCircleIcon.c06c8dcc.js";import"./PencilSquareIcon.cba1f553.js";import"./PauseCircleIcon.498a9cdf.js";import"./PlayCircleIcon.ca5c078a.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.32e065c4.js";const G=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Vend ",-1),H={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},J={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},W={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},X=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),Y=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Del Product Mapping ",-1),ee={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},te={class:"mt-3"},le={class:"flex space-x-1"},oe=e("span",null," Search ",-1),ne=e("span",null," Reset ",-1),ae={class:"flex flex-col space-y-2"},se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},re=e("span",null,"Showing",-1),de={class:"font-medium"},ie=e("span",null,"to",-1),ue={class:"font-medium"},me=e("span",null,"of",-1),ce={class:"font-medium"},pe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},fe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ge={class:"min-w-full border-separate",style:{"border-spacing":"0"}},he={class:"bg-gray-100"},ye={class:"divide-x divide-gray-200"},xe={class:"bg-white"},be={key:0},Pe=e("br",null,null,-1),Ve={key:1},ke=["onClick"],we={key:0},Ce=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Me=[Ce],Ge={__name:"Index",props:{deliveryProductMappingVends:Object,deliveryPlatformOperatorOptions:Object,deliveryProductMappingOptions:Object},setup(a){const C=a,o=c({vend_code:"",delivery_platform_operator_id:"",delivery_product_mapping_id:"",platform_ref_id:"",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),x=c([]);c();const b=c([]),M=c();I().props.auth.operatorCountry;const P=c([]),v=c(!1);I().props.auth.permissions,c(),Q(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=P.value[0],x.value=[{id:"all",name:"All"},...C.deliveryPlatformOperatorOptions.data.map(i=>({id:i.id,name:i.deliveryPlatform.name+" ("+i.type+")"}))],o.value.delivery_platform_operator_id=x.value[0],b.value=[{id:"all",name:"All"},...C.deliveryProductMappingOptions.data.map(i=>({id:i.id,name:i.name}))],o.value.delivery_product_mapping_id=b.value[0]});function F(i){M.value=i,v.value=!0}function A(){v.value=!1}function O(){N.get("/delivery-product-mapping-vends",{...o.value,delivery_platform_operator_id:o.value.delivery_platform_operator_id.id,delivery_product_mapping_id:o.value.delivery_product_mapping_id.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){N.get("/delivery-product-mapping-vends")}return(i,s)=>(d(),u(w,null,[l(k(T),{title:"Delivery Platform Vend"}),l(q,null,{header:n(()=>[G]),default:n(()=>{var S,B;return[e("div",H,[e("div",J,[e("div",W,[e("div",null,[X,l(V,{modelValue:o.value.delivery_platform_operator_id,"onUpdate:modelValue":s[0]||(s[0]=t=>o.value.delivery_platform_operator_id=t),options:x.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[Y,l(V,{modelValue:o.value.delivery_product_mapping_id,"onUpdate:modelValue":s[1]||(s[1]=t=>o.value.delivery_product_mapping_id=t),options:b.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l(D,{placeholderStr:"Vend ID",modelValue:o.value.vend_code,"onUpdate:modelValue":s[2]||(s[2]=t=>o.value.vend_code=t)},{default:n(()=>[r(" Vend ID ")]),_:1},8,["modelValue"]),l(D,{placeholderStr:"Platform ID",modelValue:o.value.platform_ref_id,"onUpdate:modelValue":s[3]||(s[3]=t=>o.value.platform_ref_id=t)},{default:n(()=>[r(" Platform ID ")]),_:1},8,["modelValue"])]),e("div",ee,[e("div",te,[e("div",le,[l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[4]||(s[4]=t=>O())},{default:n(()=>[l(k(K),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1}),l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[5]||(s[5]=t=>R())},{default:n(()=>[l(k(Z),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1})])]),e("div",ae,[e("p",se,[re,e("span",de,m((S=a.deliveryProductMappingVends.meta.from)!=null?S:0),1),ie,e("span",ue,m((B=a.deliveryProductMappingVends.meta.to)!=null?B:0),1),me,e("span",ce,m(a.deliveryProductMappingVends.meta.total),1),pe]),l(V,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[6]||(s[6]=t=>o.value.numberPerPage=t),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:O},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",ve,[e("div",fe,[e("table",ge,[e("thead",he,[e("tr",ye,[l(p,null,{default:n(()=>[r(" # ")]),_:1}),l(p,null,{default:n(()=>[r(" Vend ID ")]),_:1}),l(p,null,{default:n(()=>[r(" Name ")]),_:1}),l(p,null,{default:n(()=>[r(" Channel Status ")]),_:1}),l(p,null,{default:n(()=>[r(" VM Status ")]),_:1}),l(p,null,{default:n(()=>[r(" Platform ID ")]),_:1}),l(p)])]),e("tbody",xe,[(d(!0),u(w,null,L(a.deliveryProductMappingVends.data,(t,_)=>(d(),u("tr",{key:t.id,class:"divide-x divide-gray-200"},[l(h,{currentIndex:_,totalLength:a.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[r(m(a.deliveryProductMappingVends.meta.from+_),1)]),_:2},1032,["currentIndex","totalLength"]),l(h,{currentIndex:_,totalLength:a.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[r(m(t.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),l(h,{currentIndex:_,totalLength:a.deliveryProductMappingVends.length,inputClass:"text-left"},{default:n(()=>[t.vend&&t.vend.latestVendBinding&&t.vend.latestVendBinding.customer?(d(),u("span",be,[r(m(t.vend.latestVendBinding.customer.code)+" ",1),Pe,r(" "+m(t.vend.latestVendBinding.customer.name),1)])):(d(),u("span",Ve,m(t.vend.name),1))]),_:2},1032,["currentIndex","totalLength"]),l(h,{currentIndex:_,totalLength:a.deliveryProductMappingVends.length,inputClass:"text-left"},{default:n(()=>[t.deliveryProductMappingVendChannels?(d(),u("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:f=>F(t)},[(d(!0),u(w,null,L(t.deliveryProductMappingVendChannels,(f,g)=>(d(),u("li",{class:U(["quick-look",[g>0&&String(f.vend_channel_code)[0]!==String(t.deliveryProductMappingVendChannels[g-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:U([[g>0&&String(f.vend_channel_code)[0]!==String(t.deliveryProductMappingVendChannels[g-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-2"])},[e("span",null," #"+m(f.vend_channel_code),1)],2)],2))),256))],8,ke)):y("",!0)]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.deliveryProductMappingVends.data.length?y("",!0):(d(),u("tr",we,Me))])]),a.deliveryProductMappingVends.data.length?(d(),j(E,{key:0,links:a.deliveryProductMappingVends.links,meta:a.deliveryProductMappingVends.meta},null,8,["links","meta"])):y("",!0)])])])]),v.value?(d(),j(z,{key:0,deliveryProductMappingVendModel:M.value,showModal:v.value,onModalClose:A},null,8,["deliveryProductMappingVendModel","showModal"])):y("",!0)]}),_:1})],64))}};export{Ge as default};
