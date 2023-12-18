import{_ as H}from"./Authenticated.f95a4d63.js";import{_ as S}from"./Button.efbb67e6.js";import{_ as U}from"./DatePicker.2d4101f7.js";import{_ as Q}from"./Paginator.06453bfe.js";import{_ as L,r as G}from"./SearchInput.6537c660.js";import{_ as b}from"./MultiSelect.7acd6aa5.js";import{o as i,f as m,b as e,g,S as M,K as j,h as J,a,u as h,w as s,F as I,Z as W,d as n,c as C,l as p,t as d,k as K,O as F,i as X,n as ee}from"./app.48d9ffa2.js";import te from"./Complaint.13caca5d.js";import{_ as v,a as _}from"./TableData.5f35126c.js";import{_ as T}from"./TableHeadSort.87bf7f40.js";import{r as le}from"./BackspaceIcon.8c1acd45.js";import{r as ae}from"./ArrowDownTrayIcon.c4eb58f9.js";import"./open-closed.3bf9ea45.js";import"./use-resolve-button-type.b46b8d92.js";import"./RectangleStackIcon.254383ee.js";import"./main.3029d755.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.3525a4db.js";import"./Modal.5bd75e87.js";import"./disposables.2d104f94.js";import"./ArrowUturnLeftIcon.8768280b.js";function oe(r,w){return i(),m("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M10 2c-2.236 0-4.43.18-6.57.524C1.993 2.755 1 4.014 1 5.426v5.148c0 1.413.993 2.67 2.43 2.902.848.137 1.705.248 2.57.331v3.443a.75.75 0 001.28.53l3.58-3.579a.78.78 0 01.527-.224 41.202 41.202 0 005.183-.5c1.437-.232 2.43-1.49 2.43-2.903V5.426c0-1.413-.993-2.67-2.43-2.902A41.289 41.289 0 0010 2zm0 7a1 1 0 100-2 1 1 0 000 2zM8 8a1 1 0 11-2 0 1 1 0 012 0zm5 1a1 1 0 100-2 1 1 0 000 2z","clip-rule":"evenodd"})])}const se=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Orders ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},re={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ie=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),ue=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Status ",-1),ce=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Has Complaint? ",-1),me={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},_e={class:"mt-3"},pe={class:"flex space-x-1"},fe=e("span",null," Search ",-1),he=e("span",null," Reset ",-1),ve={class:"flex space-x-1"},ge={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},xe=e("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),ye=e("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),be=[xe,ye],Ce=e("span",null," Export Excel ",-1),we={class:"flex flex-col space-y-2"},ke={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ve=e("span",null,"Showing",-1),Pe={class:"font-medium"},De=e("span",null,"to",-1),Se={class:"font-medium"},Le=e("span",null,"of",-1),Me={class:"font-medium"},Ie=e("span",null,"results",-1),Be={class:"mt-6 flex flex-col"},Oe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},$e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ne={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ye={class:"bg-gray-100"},Ue={class:"divide-x divide-gray-200"},je=e("br",null,null,-1),Ke={class:"bg-white"},Fe={key:0},Te=e("br",null,null,-1),ze={class:"flex flex-col"},Ae={class:"font-semibold"},Ee={class:"divide-y divide-gray-200"},Ze={class:"flex py-1 px-3 space-x-2"},Re={class:"self-center font-semibold text-blue-700"},qe={key:0},He=e("br",null,null,-1),Qe={class:"flex self-center"},Ge=["href"],Je=["src"],We=e("span",{class:"self-center"}," x ",-1),Xe={class:"self-center"},et={class:"flex flex-col space-y-1"},tt=["onClick"],lt={class:"flex space-x-1"},at=e("span",{class:"font-semibold"}," Complaint ",-1),ot={key:0},st=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),nt=[st],Dt={__name:"Index",props:{deliveryPlatformOrders:Object,deliveryPlatformOperatorOptions:Object,deliveryPlatformOrderStatusOptions:Object},setup(r){const w=r,k=g([]),l=g({order_id:"",short_order_id:"",vend_code:"",date_from:M().format("YYYY-MM-DD"),date_to:M().format("YYYY-MM-DD"),delivery_platform_operator_id:"",has_complaint:"all",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),V=g([]),x=g(!1),B=g();g("");const O=j().props.auth.operatorCountry,P=g([]),z=j().props.auth.permissions,y=g(!1);J(()=>{k.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=P.value[0],V.value=[{id:"all",name:"All"},...w.deliveryPlatformOperatorOptions.data.map(u=>({id:u.id,name:u.deliveryPlatform.name+" ("+u.type+")"}))],l.value.delivery_platform_operator_id=V.value[0],l.value.has_complaint=k.value[0],l.value.status=w.deliveryPlatformOrderStatusOptions[0]});function A(u){B.value=u,y.value=!0}function E(){y.value=!1}function Z(){x.value=!0,axios({method:"get",url:"/delivery-platform-orders/excel",params:{...l.value,delivery_platform_operator_id:l.value.delivery_platform_operator_id.id,has_complaint:l.value.has_complaint.id,status:l.value.status.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"Delivery_Platform_Order_"+M().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{x.value=!1})}function D(){F.get("/delivery-platform-orders",{...l.value,delivery_platform_operator_id:l.value.delivery_platform_operator_id.id,status:l.value.status.id,has_complaint:l.value.has_complaint.id,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){F.get("/delivery-platform-orders")}function $(u){l.value.sortKey=u,l.value.sortBy=!l.value.sortBy,D()}function q(u){let o="";switch(u){case 1:case 2:o="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:case 6:o="bg-yellow-400 text-gray-800";break;case 7:o="bg-green-400 text-white-800";break;case 98:case 99:o="bg-red-400 text-white-800";break}return o}return(u,o)=>(i(),m(I,null,[a(h(W),{title:"Delivery Platform"}),a(H,null,{header:s(()=>[se]),default:s(()=>{var N,Y;return[e("div",ne,[e("div",re,[e("div",de,[a(L,{placeholderStr:"Name",modelValue:l.value.order_id,"onUpdate:modelValue":o[0]||(o[0]=t=>l.value.order_id=t)},{default:s(()=>[n(" Order ID ")]),_:1},8,["modelValue"]),a(L,{placeholderStr:"Name",modelValue:l.value.short_order_id,"onUpdate:modelValue":o[1]||(o[1]=t=>l.value.short_order_id=t)},{default:s(()=>[n(" Short Order ID ")]),_:1},8,["modelValue"]),a(L,{placeholderStr:"Vend ID",modelValue:l.value.vend_code,"onUpdate:modelValue":o[2]||(o[2]=t=>l.value.vend_code=t)},{default:s(()=>[n(" Vend ID ")]),_:1},8,["modelValue"]),a(U,{modelValue:l.value.date_from,"onUpdate:modelValue":o[3]||(o[3]=t=>l.value.date_from=t)},{default:s(()=>[n(" From ")]),_:1},8,["modelValue"]),a(U,{modelValue:l.value.date_to,"onUpdate:modelValue":o[4]||(o[4]=t=>l.value.date_to=t),minDate:l.value.date_from},{default:s(()=>[n(" To ")]),_:1},8,["modelValue","minDate"]),e("div",null,[ie,a(b,{modelValue:l.value.delivery_platform_operator_id,"onUpdate:modelValue":o[5]||(o[5]=t=>l.value.delivery_platform_operator_id=t),options:V.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ue,a(b,{modelValue:l.value.status,"onUpdate:modelValue":o[6]||(o[6]=t=>l.value.status=t),options:r.deliveryPlatformOrderStatusOptions,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ce,a(b,{modelValue:l.value.has_complaint,"onUpdate:modelValue":o[7]||(o[7]=t=>l.value.has_complaint=t),options:k.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",me,[e("div",_e,[e("div",pe,[a(S,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[8]||(o[8]=t=>D())},{default:s(()=>[a(h(G),{class:"h-4 w-4","aria-hidden":"true"}),fe]),_:1}),a(S,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[9]||(o[9]=t=>R())},{default:s(()=>[a(h(le),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1}),h(z).includes("export excel")?(i(),C(S,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:o[10]||(o[10]=t=>Z())},{default:s(()=>[e("div",ve,[e("div",null,[x.value?p("",!0):(i(),C(h(ae),{key:0,class:"h-4 w-4","aria-hidden":"true"})),x.value?(i(),m("svg",ge,be)):p("",!0)]),Ce])]),_:1})):p("",!0)])]),e("div",we,[e("p",ke,[Ve,e("span",Pe,d((N=r.deliveryPlatformOrders.meta.from)!=null?N:0),1),De,e("span",Se,d((Y=r.deliveryPlatformOrders.meta.to)!=null?Y:0),1),Le,e("span",Me,d(r.deliveryPlatformOrders.meta.total),1),Ie]),a(b,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":o[11]||(o[11]=t=>l.value.numberPerPage=t),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:D},null,8,["modelValue","options"])])])]),e("div",Be,[e("div",Oe,[e("div",$e,[e("table",Ne,[e("thead",Ye,[e("tr",Ue,[a(v,null,{default:s(()=>[n(" # ")]),_:1}),a(T,{modelName:"order_created_at",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[12]||(o[12]=t=>$("order_created_at"))},{default:s(()=>[n(" Order Time ")]),_:1},8,["sortKey","sortBy"]),a(v,null,{default:s(()=>[n(" Platform ")]),_:1}),a(v,null,{default:s(()=>[n(" Order ID ")]),_:1}),a(v,null,{default:s(()=>[n(" Short Order ID ")]),_:1}),a(v,null,{default:s(()=>[n(" Status ")]),_:1}),a(T,{modelName:"vend_code",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[13]||(o[13]=t=>$("vend_code"))},{default:s(()=>[n(" Vend ")]),_:1},8,["sortKey","sortBy"]),a(v,null,{default:s(()=>[n(" Transaction "),je,n(" Order ID ")]),_:1}),a(v,null,{default:s(()=>[n(" (Channel) Item x Qty ")]),_:1}),a(v,null,{default:s(()=>[n(" Subtotal ")]),_:1}),a(v,null,{default:s(()=>[n(" Driver Phone Number ")]),_:1})])]),e("tbody",Ke,[(i(!0),m(I,null,K(r.deliveryPlatformOrders.data,(t,c)=>(i(),m("tr",{key:t.id,class:"divide-x divide-gray-200"},[a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[n(d(r.deliveryPlatformOrders.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[n(d(t.order_created_at),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[n(d(t&&t.deliveryPlatform?t.deliveryPlatform.name:null)+" ",1),t.deliveryPlatformOperator?(i(),m("span",Fe,[Te,n("("+d(t.deliveryPlatformOperator?t.deliveryPlatformOperator.type:null)+") ",1)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[a(h(X),{href:"/delivery-platform-orders/"+t.id+"/edit",class:"text-blue-600"},{default:s(()=>[n(d(t.order_id),1)]),_:2},1032,["href"])]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[n(d(t.short_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[e("div",{class:ee(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",q(t.status)])},[e("div",ze,[e("span",Ae,d(t.status_name),1)])],2)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:s(()=>[n(d(t.deliveryProductMappingVend.vend.full_name),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[n(d(t.vend_transaction_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:s(()=>[e("ul",Ee,[(i(!0),m(I,null,K(t.deliveryPlatformOrderItems,f=>(i(),m("li",Ze,[e("span",Re,[f.orderItemVendChannels[0]?(i(),m("span",qe," (#"+d(f.orderItemVendChannels[0].vend_channel_code)+") ",1)):p("",!0)]),e("span",null,[n(d(f.deliveryProductMappingItem.product.code)+" ",1),He,n(" "+d(f.deliveryProductMappingItem.product.name),1)]),e("div",Qe,[f.deliveryProductMappingItem.product.thumbnail?(i(),m("a",{key:0,href:f.deliveryProductMappingItem.product.thumbnail.full_url,target:"_blank"},[e("img",{class:"object-scale-down h-24 w-24 md:h-16 md:w-20 rounded-full",src:f.deliveryProductMappingItem.product.thumbnail.full_url,alt:""},null,8,Je)],8,Ge)):p("",!0)]),We,e("span",Xe,d(f.qty),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-right"},{default:s(()=>[n(d(t.subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:h(O).is_currency_exponent_hidden?0:h(O).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"]),a(_,{currentIndex:c,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:s(()=>[e("div",et,[e("span",null,d(t.driver_phone_number),1),t.deliveryPlatformOrderComplaint?(i(),m("div",{key:0,class:"inline-flex justify-center items-center rounded px-1.5 py-1 text-xs font-medium border min-w-full bg-yellow-400 text-gray-800 hover:cursor-pointer",onClick:f=>A(t)},[e("div",lt,[a(h(oe),{class:"h-4 w-4","aria-hidden":"true"}),at])],8,tt)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.deliveryPlatformOrders.data.length?p("",!0):(i(),m("tr",ot,nt))])]),r.deliveryPlatformOrders.data.length?(i(),C(Q,{key:0,links:r.deliveryPlatformOrders.links,meta:r.deliveryPlatformOrders.meta},null,8,["links","meta"])):p("",!0)])])])]),y.value?(i(),C(te,{key:0,model:B.value,showModal:y.value,onModalClose:E},null,8,["model","showModal"])):p("",!0)]}),_:1})],64))}};export{Dt as default};
