import{_ as K}from"./Authenticated.69f7f963.js";import{_ as I}from"./Button.23f7c4aa.js";import{_ as D}from"./DatePicker.953b28aa.js";import{_ as Y}from"./Paginator.0c9a8688.js";import{_ as b,r as F}from"./SearchInput.9631f5a9.js";import{_ as T}from"./MultiSelect.b191c470.js";import{g,S,K as U,h as j,f as m,a as t,u as h,w as l,F as V,o as c,Z as A,b as e,d as n,t as d,k as P,l as p,c as R,O as B,i as q,n as z}from"./app.59b044b8.js";import{_,a as i}from"./TableData.98598b1e.js";import{_ as $}from"./TableHeadSort.efd96308.js";import{r as E}from"./BackspaceIcon.2eb46dcd.js";import"./open-closed.5c100063.js";import"./use-resolve-button-type.0681d336.js";import"./RectangleStackIcon.5e6bda85.js";import"./main.3db5a014.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.80550338.js";const O=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Orders ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},G={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},H={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},J={class:"mt-3"},W={class:"flex space-x-1"},X=e("span",null," Search ",-1),ee=e("span",null," Reset ",-1),te={class:"flex flex-col space-y-2"},se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},le=e("span",null,"Showing",-1),ne={class:"font-medium"},ae=e("span",null,"to",-1),oe={class:"font-medium"},re=e("span",null,"of",-1),de={class:"font-medium"},ue=e("span",null,"results",-1),ie={class:"mt-6 flex flex-col"},ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},me={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},_e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},fe={class:"bg-gray-100"},he={class:"divide-x divide-gray-200"},ge=e("br",null,null,-1),pe={class:"bg-white"},xe={key:0},ve=e("br",null,null,-1),ye={class:"flex flex-col"},be={class:"font-semibold"},Ve={class:"divide-y divide-gray-200"},we={class:"flex py-1 px-3 space-x-2"},Le={class:"self-center font-semibold text-blue-700"},ke={key:0},Ce=e("br",null,null,-1),Ie={class:"flex self-center"},De=["href"],Se=["src"],Pe=e("span",{class:"self-center"}," x ",-1),Be={class:"self-center"},$e={key:0},Me=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ne=[Me],He={__name:"Index",props:{deliveryPlatformOrders:Object},setup(r){const o=g({order_id:"",short_order_id:"",vend_code:"",date_from:S().format("YYYY-MM-DD"),date_to:S().format("YYYY-MM-DD"),sortKey:"",sortBy:!0,numberPerPage:100});g(!1),g(),g("");const w=U().props.auth.operatorCountry,v=g([]);j(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=v.value[0]});function y(){B.get("/delivery-platform-orders",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){B.get("/delivery-platform-orders")}function L(x){o.value.sortKey=x,o.value.sortBy=!o.value.sortBy,y()}function N(x){let a="";switch(x){case 1:case 2:a="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:case 6:a="bg-yellow-400 text-gray-800";break;case 7:a="bg-green-400 text-white-800";break;case 98:case 99:a="bg-red-400 text-white-800";break}return a}return(x,a)=>(c(),m(V,null,[t(h(A),{title:"Delivery Platform"}),t(K,null,{header:l(()=>[O]),default:l(()=>{var k,C;return[e("div",Q,[e("div",Z,[e("div",G,[t(b,{placeholderStr:"Name",modelValue:o.value.order_id,"onUpdate:modelValue":a[0]||(a[0]=s=>o.value.order_id=s)},{default:l(()=>[n(" Order ID ")]),_:1},8,["modelValue"]),t(b,{placeholderStr:"Name",modelValue:o.value.short_order_id,"onUpdate:modelValue":a[1]||(a[1]=s=>o.value.short_order_id=s)},{default:l(()=>[n(" Short Order ID ")]),_:1},8,["modelValue"]),t(b,{placeholderStr:"Vend ID",modelValue:o.value.vend_code,"onUpdate:modelValue":a[2]||(a[2]=s=>o.value.vend_code=s)},{default:l(()=>[n(" Vend ID ")]),_:1},8,["modelValue"]),t(D,{modelValue:o.value.date_from,"onUpdate:modelValue":a[3]||(a[3]=s=>o.value.date_from=s)},{default:l(()=>[n(" From ")]),_:1},8,["modelValue"]),t(D,{modelValue:o.value.date_to,"onUpdate:modelValue":a[4]||(a[4]=s=>o.value.date_to=s),minDate:o.value.date_from},{default:l(()=>[n(" To ")]),_:1},8,["modelValue","minDate"])]),e("div",H,[e("div",J,[e("div",W,[t(I,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[5]||(a[5]=s=>y())},{default:l(()=>[t(h(F),{class:"h-4 w-4","aria-hidden":"true"}),X]),_:1}),t(I,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[6]||(a[6]=s=>M())},{default:l(()=>[t(h(E),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})])]),e("div",te,[e("p",se,[le,e("span",ne,d((k=r.deliveryPlatformOrders.meta.from)!=null?k:0),1),ae,e("span",oe,d((C=r.deliveryPlatformOrders.meta.to)!=null?C:0),1),re,e("span",de,d(r.deliveryPlatformOrders.meta.total),1),ue]),t(T,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":a[7]||(a[7]=s=>o.value.numberPerPage=s),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",ie,[e("div",ce,[e("div",me,[e("table",_e,[e("thead",fe,[e("tr",he,[t(_,null,{default:l(()=>[n(" # ")]),_:1}),t($,{modelName:"order_created_at",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[8]||(a[8]=s=>L("order_created_at"))},{default:l(()=>[n(" Order Time ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:l(()=>[n(" Platform ")]),_:1}),t(_,null,{default:l(()=>[n(" Order ID ")]),_:1}),t(_,null,{default:l(()=>[n(" Short Order ID ")]),_:1}),t(_,null,{default:l(()=>[n(" Status ")]),_:1}),t($,{modelName:"vend_code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[9]||(a[9]=s=>L("vend_code"))},{default:l(()=>[n(" Vend ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:l(()=>[n(" Transactions "),ge,n(" Order ID ")]),_:1}),t(_,null,{default:l(()=>[n(" (Channel) Item x Qty ")]),_:1}),t(_,null,{default:l(()=>[n(" Subtotal ")]),_:1}),t(_,null,{default:l(()=>[n(" Driver Phone Number ")]),_:1})])]),e("tbody",pe,[(c(!0),m(V,null,P(r.deliveryPlatformOrders.data,(s,u)=>(c(),m("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(r.deliveryPlatformOrders.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(s.order_created_at),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(s&&s.deliveryPlatform?s.deliveryPlatform.name:null)+" ",1),s.deliveryPlatformOperator?(c(),m("span",xe,[ve,n("("+d(s.deliveryPlatformOperator?s.deliveryPlatformOperator.type:null)+") ",1)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[t(h(q),{href:"/delivery-platform-orders/"+s.id+"/edit",class:"text-blue-600"},{default:l(()=>[n(d(s.order_id),1)]),_:2},1032,["href"])]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(s.short_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[e("div",{class:z(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",N(s.status)])},[e("div",ye,[e("span",be,d(s.status_name),1)])],2)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:l(()=>[n(d(s.deliveryProductMappingVend.vend.full_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(s.vend_transaction_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:l(()=>[e("ul",Ve,[(c(!0),m(V,null,P(s.deliveryPlatformOrderItems,f=>(c(),m("li",we,[e("span",Le,[f.orderItemVendChannels[0]?(c(),m("span",ke," (#"+d(f.orderItemVendChannels[0].vend_channel_code)+") ",1)):p("",!0)]),e("span",null,[n(d(f.deliveryProductMappingItem.product.code)+" ",1),Ce,n(" "+d(f.deliveryProductMappingItem.product.name),1)]),e("div",Ie,[f.deliveryProductMappingItem.product.thumbnail?(c(),m("a",{key:0,href:f.deliveryProductMappingItem.product.thumbnail.full_url,target:"_blank"},[e("img",{class:"object-scale-down h-24 w-24 md:h-16 md:w-20 rounded-full",src:f.deliveryProductMappingItem.product.thumbnail.full_url,alt:""},null,8,Se)],8,De)):p("",!0)]),Pe,e("span",Be,d(f.qty),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-right"},{default:l(()=>[n(d(s.subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:h(w).is_currency_exponent_hidden?0:h(w).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"]),t(i,{currentIndex:u,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[n(d(s.driver_phone_number),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.deliveryPlatformOrders.data.length?p("",!0):(c(),m("tr",$e,Ne))])]),r.deliveryPlatformOrders.data.length?(c(),R(Y,{key:0,links:r.deliveryPlatformOrders.links,meta:r.deliveryPlatformOrders.meta},null,8,["links","meta"])):p("",!0)])])])])]}),_:1})],64))}};export{He as default};
