import{_ as N}from"./Authenticated.6ddd1225.js";import{_ as S}from"./Button.6fc44c4c.js";import{_ as I}from"./DatePicker.e5079e5d.js";import{_ as O}from"./Paginator.a5f238e8.js";import{_ as y,r as Y}from"./SearchInput.22c9f24c.js";import{_ as F}from"./MultiSelect.e6ebc88f.js";import{g as h,S as D,K as U,h as T,f,a as t,u as g,w as l,F as b,o as c,Z as j,b as e,d as r,t as d,k as B,l as w,c as A,O as C,i as R,n as q}from"./app.eb24236b.js";import{_,a as u}from"./TableData.c9706f14.js";import{_ as $}from"./TableHeadSort.670ba2de.js";import{r as z}from"./BackspaceIcon.491c517b.js";import"./open-closed.768a5796.js";import"./use-resolve-button-type.d5801102.js";import"./RectangleStackIcon.092f53ba.js";import"./main.f4ad6127.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.f1293c8d.js";const E=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Orders ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},G={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},H={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},J={class:"mt-3"},W={class:"flex space-x-1"},X=e("span",null," Search ",-1),ee=e("span",null," Reset ",-1),te={class:"flex flex-col space-y-2"},se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},le=e("span",null,"Showing",-1),ae={class:"font-medium"},oe=e("span",null,"to",-1),re={class:"font-medium"},ne=e("span",null,"of",-1),de={class:"font-medium"},ie=e("span",null,"results",-1),ue={class:"mt-6 flex flex-col"},ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},me={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},fe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},_e={class:"bg-gray-100"},ge={class:"divide-x divide-gray-200"},he={class:"bg-white"},pe={class:"flex flex-col"},xe={class:"font-semibold"},ve={class:"divide-y divide-gray-200"},ye={class:"flex py-1 px-3 space-x-2"},be={class:"self-center font-semibold"},we=e("br",null,null,-1),Pe={class:"flex self-center"},ke=["href"],Ve=["src"],Le=e("span",{class:"self-center"}," x ",-1),Se={class:"self-center"},Ie={key:0},De=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Be=[De],Ee={__name:"Index",props:{deliveryPlatformOrders:Object},setup(n){const o=h({order_id:"",short_order_id:"",vend_code:"",date_from:D().format("YYYY-MM-DD"),date_to:D().format("YYYY-MM-DD"),sortKey:"",sortBy:!0,numberPerPage:100});h(!1),h(),h("");const P=U().props.auth.operatorCountry,x=h([]);T(()=>{x.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=x.value[0]});function v(){C.get("/delivery-platform-orders",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){C.get("/delivery-platform-orders")}function k(p){o.value.sortKey=p,o.value.sortBy=!o.value.sortBy,v()}function K(p){let a="";switch(p){case 1:case 2:a="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:a="bg-yellow-400 text-gray-800";break;case 6:a="bg-green-400 text-white-800";break;case 98:case 99:a="bg-red-400 text-white-800";break}return a}return(p,a)=>(c(),f(b,null,[t(g(j),{title:"Delivery Platform"}),t(N,null,{header:l(()=>[E]),default:l(()=>{var V,L;return[e("div",Q,[e("div",Z,[e("div",G,[t(y,{placeholderStr:"Name",modelValue:o.value.order_id,"onUpdate:modelValue":a[0]||(a[0]=s=>o.value.order_id=s)},{default:l(()=>[r(" Order ID ")]),_:1},8,["modelValue"]),t(y,{placeholderStr:"Name",modelValue:o.value.short_order_id,"onUpdate:modelValue":a[1]||(a[1]=s=>o.value.short_order_id=s)},{default:l(()=>[r(" Short Order ID ")]),_:1},8,["modelValue"]),t(y,{placeholderStr:"Vend ID",modelValue:o.value.vend_code,"onUpdate:modelValue":a[2]||(a[2]=s=>o.value.vend_code=s)},{default:l(()=>[r(" Vend ID ")]),_:1},8,["modelValue"]),t(I,{modelValue:o.value.date_from,"onUpdate:modelValue":a[3]||(a[3]=s=>o.value.date_from=s)},{default:l(()=>[r(" From ")]),_:1},8,["modelValue"]),t(I,{modelValue:o.value.date_to,"onUpdate:modelValue":a[4]||(a[4]=s=>o.value.date_to=s),minDate:o.value.date_from},{default:l(()=>[r(" To ")]),_:1},8,["modelValue","minDate"])]),e("div",H,[e("div",J,[e("div",W,[t(S,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[5]||(a[5]=s=>v())},{default:l(()=>[t(g(Y),{class:"h-4 w-4","aria-hidden":"true"}),X]),_:1}),t(S,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[6]||(a[6]=s=>M())},{default:l(()=>[t(g(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})])]),e("div",te,[e("p",se,[le,e("span",ae,d((V=n.deliveryPlatformOrders.meta.from)!=null?V:0),1),oe,e("span",re,d((L=n.deliveryPlatformOrders.meta.to)!=null?L:0),1),ne,e("span",de,d(n.deliveryPlatformOrders.meta.total),1),ie]),t(F,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":a[7]||(a[7]=s=>o.value.numberPerPage=s),options:x.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:v},null,8,["modelValue","options"])])])]),e("div",ue,[e("div",ce,[e("div",me,[e("table",fe,[e("thead",_e,[e("tr",ge,[t(_,null,{default:l(()=>[r(" # ")]),_:1}),t($,{modelName:"order_created_at",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[8]||(a[8]=s=>k("order_created_at"))},{default:l(()=>[r(" Order Time ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:l(()=>[r(" Platform ")]),_:1}),t(_,null,{default:l(()=>[r(" Order ID ")]),_:1}),t(_,null,{default:l(()=>[r(" Short Order ID ")]),_:1}),t($,{modelName:"vend_code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[9]||(a[9]=s=>k("vend_code"))},{default:l(()=>[r(" Vend ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:l(()=>[r(" Status ")]),_:1}),t(_,null,{default:l(()=>[r(" (Channel) Item x Qty ")]),_:1}),t(_,null,{default:l(()=>[r(" Subtotal ")]),_:1})])]),e("tbody",he,[(c(!0),f(b,null,B(n.deliveryPlatformOrders.data,(s,i)=>(c(),f("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[r(d(n.deliveryPlatformOrders.meta.from+i),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[r(d(s.order_created_at),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[r(d(s&&s.deliveryPlatform?s.deliveryPlatform.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[t(g(R),{href:"/delivery-platform-orders/"+s.id+"/edit",class:"text-blue-600"},{default:l(()=>[r(d(s.order_id),1)]),_:2},1032,["href"])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[r(d(s.short_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-left"},{default:l(()=>[r(d(s.deliveryProductMappingVend.vend.full_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-center"},{default:l(()=>[e("div",{class:q(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",K(s.status)])},[e("div",pe,[e("span",xe,d(s.status_name),1)])],2)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-left"},{default:l(()=>[e("ul",ve,[(c(!0),f(b,null,B(s.orderItemVendChannels,m=>(c(),f("li",ye,[e("span",be," (#"+d(m.vend_channel_code)+") ",1),e("span",null,[r(d(m.deliveryProductMappingItem.product.code)+" ",1),we,r(" "+d(m.deliveryProductMappingItem.product.name),1)]),e("div",Pe,[m.deliveryProductMappingItem.product.thumbnail?(c(),f("a",{key:0,href:m.deliveryProductMappingItem.product.thumbnail.full_url,target:"_blank"},[e("img",{class:"h-24 w-24 md:h-16 md:w-20 rounded-full",src:m.deliveryProductMappingItem.product.thumbnail.full_url,alt:""},null,8,Ve)],8,ke)):w("",!0)]),Le,e("span",Se,d(m.qty),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:n.deliveryPlatformOrders.length,inputClass:"text-right"},{default:l(()=>[r(d(s.subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:g(P).is_currency_exponent_hidden?0:g(P).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.deliveryPlatformOrders.data.length?w("",!0):(c(),f("tr",Ie,Be))])]),n.deliveryPlatformOrders.data.length?(c(),A(O,{key:0,links:n.deliveryPlatformOrders.links,meta:n.deliveryPlatformOrders.meta},null,8,["links","meta"])):w("",!0)])])])])]}),_:1})],64))}};export{Ee as default};
