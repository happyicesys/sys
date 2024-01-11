import{_ as R}from"./Authenticated.3c41ac9c.js";import{_ as $}from"./Button.88486701.js";import{_ as U}from"./DatePicker.ee0469e2.js";import{_ as z}from"./Paginator.1e1a5d91.js";import{_ as O,r as H}from"./SearchInput.48f8d1da.js";import{_ as k}from"./MultiSelect.2b3a4877.js";import{o as i,f as u,b as e,g,U as j,Q as Y,h as G,a as l,u as h,w as o,F as b,Z as W,d as n,c as V,l as _,t as d,k as D,O as E,i as X,n as ee}from"./app.b6d96ed6.js";import te from"./Complaint.33180ccb.js";import{_ as x,a as p}from"./TableData.95fd8ac9.js";import{_ as K}from"./TableHeadSort.820462d5.js";import{r as le}from"./BackspaceIcon.dfe397b9.js";import{r as se}from"./ArrowDownTrayIcon.b3af475f.js";import"./keyboard.1cce9ba1.js";import"./use-resolve-button-type.f670a6e1.js";import"./RectangleStackIcon.b3a46605.js";import"./main.c17a4508.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.fe1aecf3.js";import"./Modal.0401d0d5.js";import"./disposables.b4f9744c.js";import"./ArrowUturnLeftIcon.c34d8c5c.js";function oe(r,L){return i(),u("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true","data-slot":"icon"},[e("path",{"fill-rule":"evenodd",d:"M10 2c-2.236 0-4.43.18-6.57.524C1.993 2.755 1 4.014 1 5.426v5.148c0 1.413.993 2.67 2.43 2.902.848.137 1.705.248 2.57.331v3.443a.75.75 0 0 0 1.28.53l3.58-3.579a.78.78 0 0 1 .527-.224 41.202 41.202 0 0 0 5.183-.5c1.437-.232 2.43-1.49 2.43-2.903V5.426c0-1.413-.993-2.67-2.43-2.902A41.289 41.289 0 0 0 10 2Zm0 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM8 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm5 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z","clip-rule":"evenodd"})])}const ae=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Orders ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},re={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ie=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),ue=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Status ",-1),ce=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Has Complaint? ",-1),me={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},_e={class:"mt-3"},pe={class:"flex space-x-1"},fe=e("span",null," Search ",-1),he=e("span",null," Reset ",-1),xe={class:"flex space-x-1"},ge={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},ve=e("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),ye=e("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),be=[ve,ye],Ce=e("span",null," Export Excel ",-1),we={class:"flex flex-col space-y-2"},ke={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ve=e("span",null,"Showing",-1),De={class:"font-medium"},Le=e("span",null,"to",-1),Se={class:"font-medium"},Ie=e("span",null,"of",-1),Pe={class:"font-medium"},Me=e("span",null,"results",-1),Be={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},$e={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Oe=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Amount (Delivered)",-1),je={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Te={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Fe=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Orders (Delivered)",-1),Ne={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ue={class:"mt-6 flex flex-col"},Ye={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},Ee={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ze={class:"bg-gray-100"},Ae={class:"divide-x divide-gray-200"},qe=e("br",null,null,-1),Je={class:"bg-white"},Qe={key:0},Re=e("br",null,null,-1),ze={class:"w-xs"},He={class:"flex flex-col"},Ge={class:"font-semibold grow-0"},We={key:0,class:"text-xs"},Xe=e("br",null,null,-1),et=e("br",null,null,-1),tt={class:"divide-y divide-gray-200"},lt={class:"flex py-1 px-3 space-x-2"},st={class:"self-center font-semibold text-blue-700"},ot={key:0},at=e("br",null,null,-1),nt=e("span",{class:"self-center"}," x ",-1),rt={class:"self-center"},dt={class:"flex flex-col space-y-1"},it={class:"inline-flex items-center rounded-md bg-purple-50 px-1 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 max-w-[130px] truncate hover:text-clip"},ut={class:"inline-flex items-center rounded px-2 py-0.5 text-xs"},ct={key:0,class:"inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border bg-red-100 text-red-800"},mt={class:"flex flex-col space-x-1"},_t={class:"font-bold"},pt={class:"flex flex-col space-y-1"},ft=["onClick"],ht={class:"flex space-x-1"},xt=e("span",{class:"font-semibold"}," Complaint ",-1),gt={key:0},vt=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),yt=[vt],Et={__name:"Index",props:{deliveryPlatformOrders:Object,deliveryPlatformOperatorOptions:Object,deliveryPlatformOrderStatusOptions:Object,totals:Object},setup(r){const L=r,S=g([]),s=g({order_id:"",short_order_id:"",vend_code:"",date_from:j().format("YYYY-MM-DD"),date_to:j().format("YYYY-MM-DD"),delivery_platform_operator_id:"",has_complaint:"all",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),I=g([]),C=g(!1),T=g();g("");const v=Y().props.auth.operatorCountry,P=g([]),Z=Y().props.auth.permissions,w=g(!1);G(()=>{S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=P.value[0],I.value=[{id:"all",name:"All"},...L.deliveryPlatformOperatorOptions.data.map(c=>({id:c.id,name:c.deliveryPlatform.name+" ("+c.type+")"}))],s.value.delivery_platform_operator_id=I.value[0],s.value.has_complaint=S.value[0],s.value.status=L.deliveryPlatformOrderStatusOptions[0]});function A(c){T.value=c,w.value=!0}function q(){w.value=!1}function J(){C.value=!0,axios({method:"get",url:"/delivery-platform-orders/excel",params:{...s.value,delivery_platform_operator_id:s.value.delivery_platform_operator_id.id,has_complaint:s.value.has_complaint.id,status:s.value.status.id},responseType:"blob"}).then(c=>{fileDownload(c.data,"Delivery_Platform_Order_"+j().format("YYMMDDhhmmss")+".xlsx")}).catch(c=>{console.log(c)}).finally(()=>{C.value=!1})}function M(){E.get("/delivery-platform-orders",{...s.value,delivery_platform_operator_id:s.value.delivery_platform_operator_id.id,status:s.value.status.id,has_complaint:s.value.has_complaint.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function Q(){E.get("/delivery-platform-orders")}function F(c){s.value.sortKey=c,s.value.sortBy=!s.value.sortBy,M()}function B(c){let a="",y="";switch(c.status){case 1:case 2:a="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:case 6:case 7:a="bg-yellow-400 text-gray-800";break;case 8:a="bg-green-400 text-white-800";break;case 98:case 99:a="bg-red-400 text-white-800",y=c.request_history_json.code+" ("+c.request_history_json.message+")";break}return{statusClass:a,statusDesc:y}}return(c,a)=>(i(),u(b,null,[l(h(W),{title:"Delivery Platform"}),l(R,null,{header:o(()=>[ae]),default:o(()=>{var y,N;return[e("div",ne,[e("div",re,[e("div",de,[l(O,{placeholderStr:"Name",modelValue:s.value.order_id,"onUpdate:modelValue":a[0]||(a[0]=t=>s.value.order_id=t)},{default:o(()=>[n(" Order ID ")]),_:1},8,["modelValue"]),l(O,{placeholderStr:"Name",modelValue:s.value.short_order_id,"onUpdate:modelValue":a[1]||(a[1]=t=>s.value.short_order_id=t)},{default:o(()=>[n(" Short Order ID ")]),_:1},8,["modelValue"]),l(O,{placeholderStr:"Vend ID",modelValue:s.value.vend_code,"onUpdate:modelValue":a[2]||(a[2]=t=>s.value.vend_code=t)},{default:o(()=>[n(" Vend ID ")]),_:1},8,["modelValue"]),l(U,{modelValue:s.value.date_from,"onUpdate:modelValue":a[3]||(a[3]=t=>s.value.date_from=t)},{default:o(()=>[n(" From ")]),_:1},8,["modelValue"]),l(U,{modelValue:s.value.date_to,"onUpdate:modelValue":a[4]||(a[4]=t=>s.value.date_to=t),minDate:s.value.date_from},{default:o(()=>[n(" To ")]),_:1},8,["modelValue","minDate"]),e("div",null,[ie,l(k,{modelValue:s.value.delivery_platform_operator_id,"onUpdate:modelValue":a[5]||(a[5]=t=>s.value.delivery_platform_operator_id=t),options:I.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ue,l(k,{modelValue:s.value.status,"onUpdate:modelValue":a[6]||(a[6]=t=>s.value.status=t),options:r.deliveryPlatformOrderStatusOptions,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ce,l(k,{modelValue:s.value.has_complaint,"onUpdate:modelValue":a[7]||(a[7]=t=>s.value.has_complaint=t),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",me,[e("div",_e,[e("div",pe,[l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[8]||(a[8]=t=>M())},{default:o(()=>[l(h(H),{class:"h-4 w-4","aria-hidden":"true"}),fe]),_:1}),l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[9]||(a[9]=t=>Q())},{default:o(()=>[l(h(le),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1}),h(Z).includes("export excel")?(i(),V($,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:a[10]||(a[10]=t=>J())},{default:o(()=>[e("div",xe,[e("div",null,[C.value?_("",!0):(i(),V(h(se),{key:0,class:"h-4 w-4","aria-hidden":"true"})),C.value?(i(),u("svg",ge,be)):_("",!0)]),Ce])]),_:1})):_("",!0)])]),e("div",we,[e("p",ke,[Ve,e("span",De,d((y=r.deliveryPlatformOrders.meta.from)!=null?y:0),1),Le,e("span",Se,d((N=r.deliveryPlatformOrders.meta.to)!=null?N:0),1),Ie,e("span",Pe,d(r.deliveryPlatformOrders.meta.total),1),Me]),l(k,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":a[11]||(a[11]=t=>s.value.numberPerPage=t),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:M},null,8,["modelValue","options"])])]),e("dl",Be,[e("div",$e,[Oe,e("dd",je,d((r.totals.total_amount/Math.pow(10,h(v).currency_exponent)).toLocaleString(void 0,{minimumFractionDigits:h(v).is_currency_exponent_hidden?0:h(v).currency_exponent})),1)]),e("div",Te,[Fe,e("dd",Ne,d(r.totals.order_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)])])]),e("div",Ue,[e("div",Ye,[e("div",Ee,[e("table",Ke,[e("thead",Ze,[e("tr",Ae,[l(x,null,{default:o(()=>[n(" # ")]),_:1}),l(K,{modelName:"order_created_at",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[12]||(a[12]=t=>F("order_created_at"))},{default:o(()=>[n(" Order Time ")]),_:1},8,["sortKey","sortBy"]),l(x,null,{default:o(()=>[n(" Platform ")]),_:1}),l(x,null,{default:o(()=>[n(" Order ID ")]),_:1}),l(x,null,{default:o(()=>[n(" Short Order ID ")]),_:1}),l(x,null,{default:o(()=>[n(" Status ")]),_:1}),l(K,{modelName:"vend_code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[13]||(a[13]=t=>F("vend_code"))},{default:o(()=>[n(" Vend ")]),_:1},8,["sortKey","sortBy"]),l(x,null,{default:o(()=>[n(" Transaction "),qe,n(" Order ID ")]),_:1}),l(x,null,{default:o(()=>[n(" (Channel) Item x Qty ")]),_:1}),l(x,null,{default:o(()=>[n(" Subtotal ")]),_:1}),l(x,null,{default:o(()=>[n(" Campaign ")]),_:1}),l(x,null,{default:o(()=>[n(" Channel Error(s) ")]),_:1}),l(x,null,{default:o(()=>[n(" Driver Phone Number ")]),_:1})])]),e("tbody",Je,[(i(!0),u(b,null,D(r.deliveryPlatformOrders.data,(t,m)=>(i(),u("tr",{key:t.id,class:"divide-x divide-gray-200"},[l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[n(d(r.deliveryPlatformOrders.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[n(d(t.order_created_at),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[n(d(t&&t.deliveryPlatform?t.deliveryPlatform.name:null)+" ",1),t.deliveryPlatformOperator?(i(),u("span",Qe,[Re,n("("+d(t.deliveryPlatformOperator?t.deliveryPlatformOperator.type:null)+") ",1)])):_("",!0)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[l(h(X),{href:"/delivery-platform-orders/"+t.id+"/edit",class:"text-blue-600"},{default:o(()=>[n(d(t.order_id),1)]),_:2},1032,["href"])]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[n(d(t.short_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center w-xs"},{default:o(()=>[e("div",ze,[e("div",{class:ee(["inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs",B(t).statusClass])},[e("div",He,[e("span",Ge,d(t.status_name),1)])],2),B(t).statusDesc?(i(),u("span",We,[Xe,n(" "+d(B(t).statusDesc),1)])):_("",!0)])]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:o(()=>[n(d(t.vend_code)+" ",1),et,n(" "+d(t.deliveryProductMappingVend.vend.cust_full_name),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[n(d(t.vend_transaction_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:o(()=>[e("ul",tt,[(i(!0),u(b,null,D(t.deliveryPlatformOrderItems,f=>(i(),u("li",lt,[e("span",st,[f.orderItemVendChannels[0]?(i(),u("span",ot," (#"+d(f.orderItemVendChannels[0].vend_channel_code)+") ",1)):_("",!0)]),e("span",null,[n(d(f.deliveryProductMappingItem.product.code)+" ",1),at,n(" "+d(f.deliveryProductMappingItem.product.name),1)]),nt,e("span",rt,d(f.qty),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-right"},{default:o(()=>[n(d(t.subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:h(v).is_currency_exponent_hidden?0:h(v).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-right"},{default:o(()=>[e("div",dt,[t.campaign_json?(i(!0),u(b,{key:0},D(t.campaign_json,f=>(i(),u("span",it,d(f.name),1))),256)):_("",!0)])]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[t.vendTransaction&&t.vendTransaction.itemsJson?(i(!0),u(b,{key:0},D(t.vendTransaction.itemsJson,f=>(i(),u("span",ut,[f.vendChannelError!=null?(i(),u("span",ct,[e("div",mt,[e("div",null,[n(" #"+d(f.vendChannelCode)+" ",1),e("span",_t,d(f.vendChannelError.desc),1)])])])):_("",!0)]))),256)):_("",!0)]),_:2},1032,["currentIndex","totalLength"]),l(p,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[e("div",pt,[e("span",null,d(t.driver_phone_number),1),t.deliveryPlatformOrderComplaint?(i(),u("div",{key:0,class:"inline-flex justify-center items-center rounded px-1.5 py-1 text-xs font-medium border min-w-full bg-yellow-400 text-gray-800 hover:cursor-pointer",onClick:f=>A(t)},[e("div",ht,[l(h(oe),{class:"h-4 w-4","aria-hidden":"true"}),xt])],8,ft)):_("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.deliveryPlatformOrders.data.length?_("",!0):(i(),u("tr",gt,yt))])]),r.deliveryPlatformOrders.data.length?(i(),V(z,{key:0,links:r.deliveryPlatformOrders.links,meta:r.deliveryPlatformOrders.meta},null,8,["links","meta"])):_("",!0)])])])]),w.value?(i(),V(te,{key:0,model:T.value,showModal:w.value,onModalClose:q},null,8,["model","showModal"])):_("",!0)]}),_:1})],64))}};export{Et as default};
