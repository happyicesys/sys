import{g as f,U,Q as A,h as K,f as m,a as t,u as v,w as n,F as Y,o as r,Z as Q,b as l,d as i,t as u,k as Z,l as g,c as x,O as h,n as q,e as M}from"./app.c4e47028.js";import{_ as G}from"./Authenticated.7e90e6af.js";import{_ as V}from"./Button.4631b684.js";import H from"./ChannelOverview.9dc75378.js";import{_ as F}from"./DatePicker.e560aea4.js";import{_ as J}from"./Paginator.f614a30a.js";import{_ as N,r as W}from"./SearchInput.dcca857e.js";import{_ as w}from"./MultiSelect.0c44488a.js";import{_ as p,a as _}from"./TableData.e3cd56cb.js";import{r as X}from"./BackspaceIcon.dae45b54.js";import{r as ee}from"./MagnifyingGlassCircleIcon.88b69069.js";import{r as te}from"./PauseCircleIcon.bbafa575.js";import{r as le}from"./PlayCircleIcon.32582746.js";import{r as ne}from"./XCircleIcon.8a169afb.js";import"./keyboard.58689cfa.js";import"./use-resolve-button-type.ef81a21b.js";import"./RectangleStackIcon.b91bd271.js";import"./FormInput.2fd92b17.js";import"./Modal.9639af57.js";import"./disposables.be045d92.js";import"./CheckCircleIcon.3fae9752.js";import"./PencilSquareIcon.ac8e2982.js";import"./main.62d9c57b.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";const ae=l("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Vend ",-1),oe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},re={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ie=l("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),de=l("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Del Product Mapping ",-1),ue=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Active? ",-1),me={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ce={class:"mt-3"},pe={class:"flex space-x-1"},_e=l("span",null," Search ",-1),fe=l("span",null," Reset ",-1),ve={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},xe=l("span",null,"Showing",-1),he={class:"font-medium"},ye=l("span",null,"to",-1),be={class:"font-medium"},ke=l("span",null,"of",-1),Ve={class:"font-medium"},we=l("span",null,"results",-1),Ce={class:"mt-6 flex flex-col"},Oe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},Le={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Pe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ie={class:"bg-gray-100"},De={class:"divide-x divide-gray-200"},Se={class:"bg-white"},$e={key:0,class:"inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-800/10"},Be={key:0},Ue={key:0},Ae={key:1},Ye={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},Me={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},Fe={class:"flex flex-col space-y-1"},Ne={key:2,class:"text-xs"},je={key:3,class:"text-xs"},Re=l("span",{class:"text-xs"},"Unbind VM",-1),Te={key:0},ze=l("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[ze],ft={__name:"Index",props:{deliveryProductMappingVends:Object,deliveryPlatformOperatorOptions:Object,deliveryProductMappingOptions:Object},setup(s){const y=s,a=f({vend_code:"",date_from:U().startOf("week").format("YYYY-MM-DD"),date_to:U().format("YYYY-MM-DD"),delivery_platform_operator_id:"",delivery_product_mapping_id:"",platform_ref_id:"",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),C=f([]),b=f([]);f();const O=f([]),I=f(),D=A().props.auth.operatorCountry,L=f([]),k=f(!1);A().props.auth.permissions,f(),K(()=>{C.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],L.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=L.value[0],b.value=[{id:"all",name:"All"},...y.deliveryPlatformOperatorOptions.data.map(d=>({id:d.id,name:d.deliveryPlatform.name+" ("+d.type+")"}))],a.value.delivery_platform_operator_id=b.value[0],O.value=[{id:"all",name:"All"},...y.deliveryProductMappingOptions.data.map(d=>({id:d.id,name:d.name}))],a.value.is_active=C.value[1],a.value.delivery_platform_operator_id=b.value[2],a.value.delivery_product_mapping_id=O.value[0]});function j(d){I.value=d,k.value=!0}function R(){k.value=!1}function S(){h.get("/delivery-product-mapping-vends",{...a.value,delivery_platform_operator_id:a.value.delivery_platform_operator_id.id,delivery_product_mapping_id:a.value.delivery_product_mapping_id.id,is_active:a.value.is_active.id,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function T(){h.get("/delivery-product-mapping-vends")}function z(d){let o=d.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(o)||h.post("/delivery-product-mappings/vends/"+d.id+"/toggle-pause-vend",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function E(d){!confirm("Are you sure to delete this entry?")||h.delete("/delivery-product-mappings/unbind/"+d,{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{h.reload({only:["unbindedVendOptions"]}),unbindedVendOptions.value=y.unbindedVendOptions?y.unbindedVendOptions.data:[]}})}return(d,o)=>(r(),m(Y,null,[t(v(Q),{title:"Delivery Platform Vend"}),t(G,null,{header:n(()=>[ae]),default:n(()=>{var P,$;return[l("div",oe,[l("div",se,[l("div",re,[l("div",null,[ie,t(w,{modelValue:a.value.delivery_platform_operator_id,"onUpdate:modelValue":o[0]||(o[0]=e=>a.value.delivery_platform_operator_id=e),options:b.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[de,t(w,{modelValue:a.value.delivery_product_mapping_id,"onUpdate:modelValue":o[1]||(o[1]=e=>a.value.delivery_product_mapping_id=e),options:O.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t(N,{placeholderStr:"Vend ID",modelValue:a.value.vend_code,"onUpdate:modelValue":o[2]||(o[2]=e=>a.value.vend_code=e)},{default:n(()=>[i(" Vend ID ")]),_:1},8,["modelValue"]),t(N,{placeholderStr:"Platform ID",modelValue:a.value.platform_ref_id,"onUpdate:modelValue":o[3]||(o[3]=e=>a.value.platform_ref_id=e)},{default:n(()=>[i(" Platform ID ")]),_:1},8,["modelValue"]),l("div",null,[ue,t(w,{modelValue:a.value.is_active,"onUpdate:modelValue":o[4]||(o[4]=e=>a.value.is_active=e),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[t(F,{modelValue:a.value.date_from,"onUpdate:modelValue":o[5]||(o[5]=e=>a.value.date_from=e)},{default:n(()=>[i(" From ")]),_:1},8,["modelValue"])]),l("div",null,[t(F,{modelValue:a.value.date_to,"onUpdate:modelValue":o[6]||(o[6]=e=>a.value.date_to=e),minDate:a.value.date_from},{default:n(()=>[i(" To ")]),_:1},8,["modelValue","minDate"])])]),l("div",me,[l("div",ce,[l("div",pe,[t(V,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[7]||(o[7]=e=>S())},{default:n(()=>[t(v(W),{class:"h-4 w-4","aria-hidden":"true"}),_e]),_:1}),t(V,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[8]||(o[8]=e=>T())},{default:n(()=>[t(v(X),{class:"h-4 w-4","aria-hidden":"true"}),fe]),_:1})])]),l("div",ve,[l("p",ge,[xe,l("span",he,u((P=s.deliveryProductMappingVends.meta.from)!=null?P:0),1),ye,l("span",be,u(($=s.deliveryProductMappingVends.meta.to)!=null?$:0),1),ke,l("span",Ve,u(s.deliveryProductMappingVends.meta.total),1),we]),t(w,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":o[9]||(o[9]=e=>a.value.numberPerPage=e),options:L.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:S},null,8,["modelValue","options"])])])]),l("div",Ce,[l("div",Oe,[l("div",Le,[l("table",Pe,[l("thead",Ie,[l("tr",De,[t(p,null,{default:n(()=>[i(" # ")]),_:1}),t(p,null,{default:n(()=>[i(" Vend ID ")]),_:1}),t(p,null,{default:n(()=>[i(" Platform ID ")]),_:1}),t(p,null,{default:n(()=>[i(" Customer ID ")]),_:1}),t(p,null,{default:n(()=>[i(" Customer Name ")]),_:1}),t(p,null,{default:n(()=>[i(" Channel ")]),_:1}),t(p,null,{default:n(()=>[i(" VM Status ")]),_:1}),t(p,null,{default:n(()=>[i(" Amount ")]),_:1}),t(p,null,{default:n(()=>[i(" Count ")]),_:1}),t(p)])]),l("tbody",Se,[(r(!0),m(Y,null,Z(s.deliveryProductMappingVends.data,(e,c)=>(r(),m("tr",{key:e.id,class:"divide-x divide-gray-200"},[t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[i(u(s.deliveryProductMappingVends.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[i(u(e.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-left"},{default:n(()=>[i(u(e.platform_ref_id)+" ",1),e.binded_times>1?(r(),m("span",$e,u(e.binded_times),1)):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[e.vend&&e.vend.latestVendBinding&&e.vend.latestVendBinding.customer?(r(),m("span",Be,u(e.vend.latestVendBinding.customer.virtual_customer_prefix)+"-"+u(e.vend.latestVendBinding.customer.virtual_customer_code),1)):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-left"},{default:n(()=>[e.vend&&e.vend.latestVendBinding&&e.vend.latestVendBinding.customer?(r(),m("span",Ue,u(e.vend.latestVendBinding.customer.name),1)):(r(),m("span",Ae,u(e.vend.name),1))]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[e.deliveryProductMappingVendChannels?(r(),x(v(ee),{key:0,class:"h-5 w-5 text-green-500 hover:cursor-pointer","aria-hidden":"true",onClick:B=>j(e)},null,8,["onClick"])):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[e.is_active==1?(r(),m("span",Ye," Operating ")):g("",!0),e.is_active==0?(r(),m("span",Me," Paused ")):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-right"},{default:n(()=>[i(u(e.delivery_platform_orders_sum_subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:v(D).is_currency_exponent_hidden?0:v(D).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-right"},{default:n(()=>[i(u(e.delivery_platform_orders_count),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:c,totalLength:s.deliveryProductMappingVends.length,inputClass:"text-center"},{default:n(()=>[l("div",Fe,[t(V,{class:q(["flex space-x-1 w-fit",[e.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:M(B=>z(e),["prevent"])},{default:n(()=>[e.is_active?(r(),x(v(te),{key:0,class:"w-3 h-3"})):(r(),x(v(le),{key:1,class:"w-3 h-3"})),e.is_active?(r(),m("span",Ne," Pause VM ")):(r(),m("span",je," Resume VM "))]),_:2},1032,["class","onClick"]),e.is_active?g("",!0):(r(),x(V,{key:0,class:"flex space-x-1 bg-red-500 hover:bg-red-600 text-white w-fit",onClick:M(B=>E(e.id),["prevent"])},{default:n(()=>[t(v(ne),{class:"w-3 h-3"}),Re]),_:2},1032,["onClick"]))])]),_:2},1032,["currentIndex","totalLength"])]))),128)),s.deliveryProductMappingVends.data.length?g("",!0):(r(),m("tr",Te,Ee))])]),s.deliveryProductMappingVends.data.length?(r(),x(J,{key:0,links:s.deliveryProductMappingVends.links,meta:s.deliveryProductMappingVends.meta},null,8,["links","meta"])):g("",!0)])])])]),k.value?(r(),x(H,{key:0,deliveryProductMappingVendModel:I.value,showModal:k.value,onModalClose:R},null,8,["deliveryProductMappingVendModel","showModal"])):g("",!0)]}),_:1})],64))}};export{ft as default};