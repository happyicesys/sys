import{_ as G}from"./Authenticated.f07d1e4c.js";import{_ as O}from"./Button.11d61cb3.js";import{d as A}from"./main.5e906914.js";import{o as i,g as c,d as e,r as F,a,b as g,V as k,i as v,l as K,j as R,w as l,F as $,H as z,p as _,c as U,t as u,m as J,f as r,W as H,J as E}from"./app.1ec5fcee.js";import{_ as P,r as Z,T as f,a as W,b as p}from"./TableData.9cc76307.js";import{_ as V}from"./MultiSelect.cc9461fc.js";import{_ as q}from"./TableHeadSort.83bcc79b.js";import{r as Q}from"./BackspaceIcon.b9349e6f.js";import{r as X}from"./ArrowDownTrayIcon.3b30a198.js";import"./open-closed.5467d859.js";import"./use-resolve-button-type.3ab32487.js";import"./RectangleStackIcon.03bad4f7.js";import"./_plugin-vue_export-helper.cdc0426e.js";function ee(s,h){return i(),c("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M15.79 14.77a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L11.832 10l3.938 3.71a.75.75 0 01.02 1.06zm-6 0a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L5.832 10l3.938 3.71a.75.75 0 01.02 1.06z","clip-rule":"evenodd"})])}function te(s,h){return i(),c("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M10.21 14.77a.75.75 0 01.02-1.06L14.168 10 10.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z","clip-rule":"evenodd"}),e("path",{"fill-rule":"evenodd",d:"M4.21 14.77a.75.75 0 01.02-1.06L8.168 10 4.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z","clip-rule":"evenodd"})])}const oe={for:"text",class:"block text-sm font-medium text-gray-700"},ae={class:"mt-1 flex rounded-md shadow-sm"},Y={__name:"DatePicker",props:{modelValue:[Date,String,Object],minDate:[Date,String,Object],maxDate:[Date,String,Object],enableTimePicker:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(s,{emit:h}){const w=s;function D(){h("update:modelValue",k(w.modelValue).subtract(1,"days").format("YYYY-MM-DD"))}function x(){h("update:modelValue",k(w.modelValue).add(1,"days").format("YYYY-MM-DD"))}function o(y){h("update:modelValue",k(y).format("YYYY-MM-DD"))}return(y,b)=>(i(),c("div",null,[e("label",oe,[F(y.$slots,"default")]),e("div",ae,[a(g(A),{modelValue:s.modelValue,"onUpdate:modelValue":o,format:"yyyy-MM-dd",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:s.minDate,maxDate:s.maxDate,enableTimePicker:s.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"]),e("button",{type:"button",class:"border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500",onClick:b[0]||(b[0]=C=>D())},[e("span",null,[a(g(ee),{class:"h-4 w-4","aria-hidden":"true"})])]),e("button",{type:"button",class:"rounded-r-md border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500",onClick:b[1]||(b[1]=C=>x())},[e("span",null,[a(g(te),{class:"h-4 w-4","aria-hidden":"true"})])])])]))}},le=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (Transactions) ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re={class:"col-span-5 md:col-span-1"},ie=r(" Vend ID "),ue=e("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ce={class:"col-span-5 md:col-span-1"},me=r(" Channel ID "),pe=e("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ge={class:"col-span-5 md:col-span-1"},he=r(" From "),_e={class:"col-span-5 md:col-span-1"},fe=r(" To "),ve={class:"col-span-5 md:col-span-1"},xe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),ye={class:"col-span-5 md:col-span-1"},be=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Payment Method ",-1),Ce={key:0,class:"col-span-5 md:col-span-1"},Ve=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ke={key:1,class:"col-span-5 md:col-span-1"},we=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),De={key:2,class:"col-span-5 md:col-span-1"},Pe=r(" Cust ID "),Me={key:3,class:"col-span-5 md:col-span-1"},Se=r(" Cust Name "),Le={key:4},Oe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Be={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ie={class:"mt-3"},$e={class:"flex space-x-1"},Ue=e("span",null," Search ",-1),Ee=e("span",null," Reset ",-1),Ye={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ne=e("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Te=e("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),je=[Ne,Te],Ge=e("span",null," Export Excel ",-1),Ae={class:"flex flex-col space-y-2"},Fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ke=e("span",null,"Showing",-1),Re={class:"font-medium"},ze=e("span",null,"to",-1),Je={class:"font-medium"},He=e("span",null,"of",-1),Ze={class:"font-medium"},We=e("span",null,"results",-1),qe={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},Qe={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Xe=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Amount (Success)",-1),et={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},tt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},ot=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Count (Success)",-1),at={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},lt={class:"mt-6 flex flex-col"},nt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},st={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},dt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},rt={class:"bg-gray-100"},it={class:"divide-x divide-gray-200"},ut=r(" # "),ct=r(" Order ID "),mt=r(" Transaction DateTime "),pt=r(" Vend ID "),gt=r(" Customer Name "),ht=r(" Channel "),_t=r(" Product Code "),ft=r(" Product Name "),vt=r(" Amount "),xt=r(" Payment Method "),yt=r(" Channels Error "),bt=r(" Payment Received "),Ct={class:"bg-white"},Vt={key:0},kt=e("br",null,null,-1),wt={key:1},Dt={key:0},Pt={key:0},Mt={key:0},St={key:1},Lt={key:0},Ot=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Bt=[Ot],zt={__name:"Transaction",props:{categories:Object,categoryGroups:Object,operatorOptions:Object,paymentMethods:Object,vends:Object,vendTransactions:Object,vendTransactionsTotal:[String,Number],vendTransactionsCount:[String,Number],vendChannelErrors:Object},setup(s){const h=s,w=v([]),D=v([]),x=K().props.value.auth.operatorRole;R(()=>{b.value=[{id:"errors_only",desc:"Errors Only"},...h.vendChannelErrors.data.map(n=>({id:n.id,desc:n.desc}))],M.value=[{id:"",name:"All"},...h.paymentMethods.data.map(n=>({id:n.id,name:n.name}))],S.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=S.value[0],o.value.paymentMethod=M.value[0],w.value=h.categories.data.map(n=>({id:n.id,name:n.name})),D.value=h.categoryGroups.data.map(n=>({id:n.id,name:n.name})),y.value=[{id:"all",full_name:"All"},...h.operatorOptions.data.map(n=>({id:n.id,full_name:n.full_name}))],o.value.operator=y.value[0]});const o=v({codes:"",channel_codes:"",categories:[],categoryGroups:[],customer_code:"",customer_name:"",errors:[],operator:"",paymentMethod:"",date_from:k().subtract(1,"days").toDate(),date_to:k().toDate(),sortKey:"",sortBy:!1,numberPerPage:100}),y=v([]),b=v([]),C=v(!1),M=v([]),S=v([]);function N(){C.value=!0,H({method:"get",url:"/vends/transactions/excel",params:{...o.value,categories:o.value.categories.map(n=>n.id),categoryGroups:o.value.categoryGroups.map(n=>n.id),channel_codes:o.value.channel_codes,errors:o.value.errors.map(n=>n.id),operator_id:o.value.operator.id,paymentMethod:o.value.paymentMethod.id,numberPerPage:o.value.numberPerPage.id},responseType:"blob"}).then(n=>{fileDownload(n.data,"Vending_Transaction_"+k().format("YYMMDDhhmmss")+".xlsx")}).catch(n=>{console.log(n)}).finally(()=>{C.value=!1})}function L(){E.Inertia.get("/vends/transactions",{...o.value,categories:o.value.categories.map(n=>n.id),categoryGroups:o.value.categoryGroups.map(n=>n.id),channel_codes:o.value.channel_codes,errors:o.value.errors.map(n=>n.id),operator_id:o.value.operator.id,paymentMethod:o.value.paymentMethod.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function T(){E.Inertia.get("/vends/transactions")}function j(n){o.value.sortKey=n,o.value.sortBy=!o.value.sortBy,L()}return(n,d)=>(i(),c($,null,[a(g(z),{title:"VM Transactions"}),a(G,null,{header:l(()=>[le]),default:l(()=>{var B,I;return[e("div",ne,[e("div",se,[e("div",de,[e("div",re,[a(P,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":d[0]||(d[0]=t=>o.value.codes=t)},{default:l(()=>[ie,ue]),_:1},8,["modelValue"])]),e("div",ce,[a(P,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":d[1]||(d[1]=t=>o.value.channel_codes=t)},{default:l(()=>[me,pe]),_:1},8,["modelValue"])]),e("div",ge,[a(Y,{modelValue:o.value.date_from,"onUpdate:modelValue":d[2]||(d[2]=t=>o.value.date_from=t)},{default:l(()=>[he]),_:1},8,["modelValue"])]),e("div",_e,[a(Y,{modelValue:o.value.date_to,"onUpdate:modelValue":d[3]||(d[3]=t=>o.value.date_to=t),minDate:o.value.date_from},{default:l(()=>[fe]),_:1},8,["modelValue","minDate"])]),e("div",ve,[xe,a(V,{modelValue:o.value.errors,"onUpdate:modelValue":d[4]||(d[4]=t=>o.value.errors=t),options:b.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ye,[be,a(V,{modelValue:o.value.paymentMethod,"onUpdate:modelValue":d[5]||(d[5]=t=>o.value.paymentMethod=t),options:M.value,valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),g(x)?_("",!0):(i(),c("div",Ce,[Ve,a(V,{modelValue:o.value.categories,"onUpdate:modelValue":d[6]||(d[6]=t=>o.value.categories=t),options:w.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),g(x)?_("",!0):(i(),c("div",ke,[we,a(V,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":d[7]||(d[7]=t=>o.value.categoryGroups=t),options:D.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),g(x)?_("",!0):(i(),c("div",De,[a(P,{placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":d[8]||(d[8]=t=>o.value.customer_code=t)},{default:l(()=>[Pe]),_:1},8,["modelValue"])])),g(x)?_("",!0):(i(),c("div",Me,[a(P,{placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":d[9]||(d[9]=t=>o.value.customer_name=t)},{default:l(()=>[Se]),_:1},8,["modelValue"])])),g(x)?_("",!0):(i(),c("div",Le,[Oe,a(V,{modelValue:o.value.operator,"onUpdate:modelValue":d[10]||(d[10]=t=>o.value.operator=t),options:y.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]))]),e("div",Be,[e("div",Ie,[e("div",$e,[a(O,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[11]||(d[11]=t=>L())},{default:l(()=>[a(g(Z),{class:"h-4 w-4","aria-hidden":"true"}),Ue]),_:1}),a(O,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[12]||(d[12]=t=>T())},{default:l(()=>[a(g(Q),{class:"h-4 w-4","aria-hidden":"true"}),Ee]),_:1}),a(O,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[13]||(d[13]=t=>N())},{default:l(()=>[C.value?_("",!0):(i(),U(g(X),{key:0,class:"h-4 w-4","aria-hidden":"true"})),C.value?(i(),c("svg",Ye,je)):_("",!0),Ge]),_:1})])]),e("div",Ae,[e("p",Fe,[Ke,e("span",Re,u((B=s.vendTransactions.meta.from)!=null?B:0),1),ze,e("span",Je,u((I=s.vendTransactions.meta.to)!=null?I:0),1),He,e("span",Ze,u(s.vendTransactions.meta.total),1),We]),a(V,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":d[14]||(d[14]=t=>o.value.numberPerPage=t),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:L},null,8,["modelValue","options"])])]),e("dl",qe,[e("div",Qe,[Xe,e("dd",et,u((s.vendTransactionsTotal/100).toLocaleString(void 0,{minimumFractionDigits:2})),1)]),e("div",tt,[ot,e("dd",at,u(s.vendTransactionsCount.toLocaleString(void 0,{minimumFractionDigits:0})),1)])])]),e("div",lt,[e("div",nt,[e("div",st,[e("table",dt,[e("thead",rt,[e("tr",it,[a(f,null,{default:l(()=>[ut]),_:1}),a(f,null,{default:l(()=>[ct]),_:1}),a(q,{modelName:"transaction_datetime",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:d[15]||(d[15]=t=>j("transaction_datetime"))},{default:l(()=>[mt]),_:1},8,["sortKey","sortBy"]),a(f,null,{default:l(()=>[pt]),_:1}),a(f,null,{default:l(()=>[gt]),_:1}),a(f,null,{default:l(()=>[ht]),_:1}),a(f,null,{default:l(()=>[_t]),_:1}),a(f,null,{default:l(()=>[ft]),_:1}),a(f,null,{default:l(()=>[vt]),_:1}),a(f,null,{default:l(()=>[xt]),_:1}),a(f,null,{default:l(()=>[yt]),_:1}),a(f,null,{default:l(()=>[bt]),_:1})])]),e("tbody",Ct,[(i(!0),c($,null,J(s.vendTransactions.data,(t,m)=>(i(),c("tr",{key:t.id,class:"divide-x divide-gray-200"},[a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(s.vendTransactions.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.order_id),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.transaction_datetime),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[t.vend.latestVendBinding&&t.vend.latestVendBinding.customer?(i(),c("span",Vt,[r(u(t.vend.latestVendBinding.customer.code)+" ",1),kt,r(" "+u(t.vend.latestVendBinding.customer.name),1)])):(i(),c("span",wt,u(t.vend.name),1))]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.vendChannel.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[t.product&&t.product.code?(i(),c("span",Dt,u(t.product.code),1)):_("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[t.product&&t.product.name?(i(),c("span",Pt,u(t.product.name),1)):_("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-right"},{default:l(()=>[r(u(t.amount),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.paymentMethod?t.paymentMethod.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(u(t.vendChannelError?t.vendChannelError.desc:0),1)]),_:2},1032,["currentIndex","totalLength"]),a(p,{currentIndex:m,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[t.is_payment_received?(i(),c("span",Mt,u(t.is_payment_received?"Successful":"Unsuccessful"),1)):(i(),c("span",St,u(t.vendTransactionJson?t.vendTransactionJson.SErr==0||t.vendTransactionJson.SErr==6?"Successful":"Unsuccessful":""),1))]),_:2},1032,["currentIndex","totalLength"])]))),128)),s.vendTransactions.data.length?_("",!0):(i(),c("tr",Lt,Bt))])]),s.vendTransactions.data.length?(i(),U(W,{key:0,links:s.vendTransactions.links,meta:s.vendTransactions.meta},null,8,["links","meta"])):_("",!0)])])])])]}),_:1})],64))}};export{zt as default};
