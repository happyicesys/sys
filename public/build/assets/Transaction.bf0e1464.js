import{o as i,g as u,d as o,r as F,a as s,b as p,a0 as k,i as v,l as $,j as R,w as a,F as E,H as z,P as S,p as f,c as Y,t as c,m as H,f as r,a1 as Z,J as j}from"./app.85f64675.js";import{_ as q}from"./Authenticated.0ed74fca.js";import{_ as I}from"./Button.70586e46.js";import{d as Q}from"./main.31013404.js";import{_ as L,r as W,T as h,a as X,b as _}from"./TableData.cd86ed9e.js";import{_ as V}from"./MultiSelect.f958bb22.js";import{_ as T}from"./TableHeadSort.fcfb793f.js";import{r as ee}from"./BackspaceIcon.89322afa.js";import{r as te}from"./ArrowDownTrayIcon.15de628d.js";import"./open-closed.f3e9263d.js";import"./use-resolve-button-type.6d97e4a0.js";import"./RectangleStackIcon.c09510e5.js";import"./_plugin-vue_export-helper.cdc0426e.js";function oe(d,g){return i(),u("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[o("path",{"fill-rule":"evenodd",d:"M15.79 14.77a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L11.832 10l3.938 3.71a.75.75 0 01.02 1.06zm-6 0a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L5.832 10l3.938 3.71a.75.75 0 01.02 1.06z","clip-rule":"evenodd"})])}function se(d,g){return i(),u("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[o("path",{"fill-rule":"evenodd",d:"M10.21 14.77a.75.75 0 01.02-1.06L14.168 10 10.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z","clip-rule":"evenodd"}),o("path",{"fill-rule":"evenodd",d:"M4.21 14.77a.75.75 0 01.02-1.06L8.168 10 4.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z","clip-rule":"evenodd"})])}const le={for:"text",class:"block text-sm font-medium text-gray-700"},ae={class:"mt-1 flex rounded-md shadow-sm"},N={__name:"DatePicker",props:{modelValue:[Date,String,Object],minDate:[Date,String,Object],maxDate:[Date,String,Object],enableTimePicker:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(d,{emit:g}){const y=d;function w(){g("update:modelValue",k(y.modelValue).subtract(1,"days").format("YYYY-MM-DD"))}function P(){g("update:modelValue",k(y.modelValue).add(1,"days").format("YYYY-MM-DD"))}function x(t){g("update:modelValue",k(t).format("YYYY-MM-DD"))}return(t,b)=>(i(),u("div",null,[o("label",le,[F(t.$slots,"default")]),o("div",ae,[s(p(Q),{modelValue:d.modelValue,"onUpdate:modelValue":x,format:"yyyy-MM-dd",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:d.minDate,maxDate:d.maxDate,enableTimePicker:d.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"]),o("button",{type:"button",class:"border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500",onClick:b[0]||(b[0]=D=>w())},[o("span",null,[s(p(oe),{class:"h-4 w-4","aria-hidden":"true"})])]),o("button",{type:"button",class:"rounded-r-md border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500",onClick:b[1]||(b[1]=D=>P())},[o("span",null,[s(p(se),{class:"h-4 w-4","aria-hidden":"true"})])])])]))}},ne=o("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (Transactions) ",-1),de={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},re={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ie={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ue={class:"col-span-5 md:col-span-1"},ce=r(" Vend ID "),me=o("span",{class:"text-[9px]"},' ("," for multiple) ',-1),pe={class:"col-span-5 md:col-span-1"},_e=r(" Channel ID "),ge=o("span",{class:"text-[9px]"},' ("," for multiple) ',-1),he={class:"col-span-5 md:col-span-1"},fe=r(" From "),ve={class:"col-span-5 md:col-span-1"},ye=r(" To "),xe={class:"col-span-5 md:col-span-1"},be=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Ve={class:"col-span-5 md:col-span-1"},Ce=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Payment Method ",-1),ke={key:0,class:"col-span-5 md:col-span-1"},we=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Pe={key:1,class:"col-span-5 md:col-span-1"},De=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Me={key:2,class:"col-span-5 md:col-span-1"},Se=r(" Cust ID "),Le={key:3,class:"col-span-5 md:col-span-1"},Be=r(" Cust Name "),Oe={key:4},$e=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Ie={key:5},Je=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Ue=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Payment Received ",-1),Ee={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ye={class:"mt-3"},je={class:"flex space-x-1"},Ne=o("span",null," Search ",-1),Ae=o("span",null," Reset ",-1),Ge={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ke=o("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Fe=o("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Re=[Ke,Fe],ze=o("span",null," Export Excel ",-1),He={class:"flex flex-col space-y-2"},Ze={class:"text-sm text-gray-700 leading-5 flex space-x-1"},qe=o("span",null,"Showing",-1),Qe={class:"font-medium"},We=o("span",null,"to",-1),Xe={class:"font-medium"},Te=o("span",null,"of",-1),et={class:"font-medium"},tt=o("span",null,"results",-1),ot={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},st={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},lt=o("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Amount (Success)",-1),at={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},nt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},dt=o("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Count (Success)",-1),rt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},it={class:"mt-6 flex flex-col"},ut={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ct={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},mt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},pt={class:"bg-gray-100"},_t={class:"divide-x divide-gray-200"},gt=r(" # "),ht=r(" Order ID "),ft=r(" Transaction DateTime "),vt=r(" Vend ID "),yt=r(" Customer Name "),xt=r(" Channel "),bt=r(" Product Code "),Vt=r(" Product Name "),Ct=r(" Amount "),kt=r(" Payment Method "),wt=r(" Channels Error "),Pt=r(" Payment Received "),Dt={class:"bg-white"},Mt={key:0},St=o("br",null,null,-1),Lt={key:1},Bt=o("br",null,null,-1),Ot={key:2},$t={key:0},It={key:1},Jt={key:2},Ut={key:0},Et={key:1},Yt={key:2},jt={key:0},Nt={key:1},At={key:0},Gt=o("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Kt=[Gt],so={__name:"Transaction",props:{categories:Object,categoryGroups:Object,operatorOptions:Object,paymentMethods:Object,vends:Object,vendTransactions:Object,totals:[Object,Array],vendChannelErrors:Object},setup(d){const g=d,y=v([]),w=v([]),P=v([]);$().props.value.auth.roles;const x=$().props.value.auth.permissions;$().props.value.auth.operatorRole,R(()=>{D.value=[{id:"errors_only",desc:"Errors Only"},...g.vendChannelErrors.data.map(n=>({id:n.id,desc:n.desc}))],B.value=[{id:"",name:"All"},...g.paymentMethods.data.map(n=>({id:n.id,name:n.name}))],O.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=O.value[0],t.value.paymentMethod=B.value[0],y.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],w.value=g.categories.data.map(n=>({id:n.id,name:n.name})),P.value=g.categoryGroups.data.map(n=>({id:n.id,name:n.name})),b.value=[{id:"all",full_name:"All"},...g.operatorOptions.data.map(n=>({id:n.id,full_name:n.full_name}))],t.value.operator=b.value[0],t.value.is_binded_customer=y.value[0],t.value.is_payment_received=y.value[0]});const t=v({codes:"",channel_codes:"",categories:[],categoryGroups:[],customer_code:"",customer_name:"",errors:[],operator:"",is_binded_customer:"",is_payment_received:"",paymentMethod:"",date_from:k().toDate(),date_to:k().toDate(),sortKey:"",sortBy:!1,numberPerPage:100}),b=v([]),D=v([]),M=v(!1),B=v([]),O=v([]);function A(){M.value=!0,Z({method:"get",url:"/vends/transactions/excel",params:{...t.value,categories:t.value.categories.map(n=>n.id),categoryGroups:t.value.categoryGroups.map(n=>n.id),channel_codes:t.value.channel_codes,errors:t.value.errors.map(n=>n.id),operator_id:t.value.operator.id,is_binded_customer:t.value.is_binded_customer.id,is_payment_received:t.value.is_payment_received.id,paymentMethod:t.value.paymentMethod.id,numberPerPage:t.value.numberPerPage.id},responseType:"blob"}).then(n=>{fileDownload(n.data,"Vending_Transaction_"+k().format("YYMMDDhhmmss")+".xlsx")}).catch(n=>{console.log(n)}).finally(()=>{M.value=!1})}function C(){j.Inertia.get("/vends/transactions",{...t.value,categories:t.value.categories.map(n=>n.id),categoryGroups:t.value.categoryGroups.map(n=>n.id),channel_codes:t.value.channel_codes,errors:t.value.errors.map(n=>n.id),operator_id:t.value.operator.id,is_binded_customer:t.value.is_binded_customer.id,is_payment_received:t.value.is_payment_received.id,paymentMethod:t.value.paymentMethod.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function G(){j.Inertia.get("/vends/transactions")}function K(n){t.value.sortKey=n,t.value.sortBy=!t.value.sortBy,C()}return(n,l)=>(i(),u(E,null,[s(p(z),{title:"VM Transactions"}),s(q,null,{header:a(()=>[ne]),default:a(()=>{var J,U;return[o("div",de,[o("div",re,[o("div",ie,[o("div",ue,[s(L,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":l[0]||(l[0]=e=>t.value.codes=e),onKeyup:l[1]||(l[1]=S(e=>C(),["enter"]))},{default:a(()=>[ce,me]),_:1},8,["modelValue"])]),o("div",pe,[s(L,{placeholderStr:"Channel ID",modelValue:t.value.channel_codes,"onUpdate:modelValue":l[2]||(l[2]=e=>t.value.channel_codes=e),onKeyup:l[3]||(l[3]=S(e=>C(),["enter"]))},{default:a(()=>[_e,ge]),_:1},8,["modelValue"])]),o("div",he,[s(N,{modelValue:t.value.date_from,"onUpdate:modelValue":l[4]||(l[4]=e=>t.value.date_from=e)},{default:a(()=>[fe]),_:1},8,["modelValue"])]),o("div",ve,[s(N,{modelValue:t.value.date_to,"onUpdate:modelValue":l[5]||(l[5]=e=>t.value.date_to=e),minDate:t.value.date_from},{default:a(()=>[ye]),_:1},8,["modelValue","minDate"])]),o("div",xe,[be,s(V,{modelValue:t.value.errors,"onUpdate:modelValue":l[6]||(l[6]=e=>t.value.errors=e),options:D.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),o("div",Ve,[Ce,s(V,{modelValue:t.value.paymentMethod,"onUpdate:modelValue":l[7]||(l[7]=e=>t.value.paymentMethod=e),options:B.value,valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),p(x).includes("admin-access transactions")?(i(),u("div",ke,[we,s(V,{modelValue:t.value.categories,"onUpdate:modelValue":l[8]||(l[8]=e=>t.value.categories=e),options:w.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),p(x).includes("admin-access transactions")?(i(),u("div",Pe,[De,s(V,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":l[9]||(l[9]=e=>t.value.categoryGroups=e),options:P.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),p(x).includes("admin-access transactions")?(i(),u("div",Me,[s(L,{placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":l[10]||(l[10]=e=>t.value.customer_code=e),onKeyup:l[11]||(l[11]=S(e=>C(),["enter"]))},{default:a(()=>[Se]),_:1},8,["modelValue"])])):f("",!0),p(x).includes("admin-access transactions")?(i(),u("div",Le,[s(L,{placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":l[12]||(l[12]=e=>t.value.customer_name=e),onKeyup:l[13]||(l[13]=S(e=>C(),["enter"]))},{default:a(()=>[Be]),_:1},8,["modelValue"])])):f("",!0),p(x).includes("admin-access transactions")?(i(),u("div",Oe,[$e,s(V,{modelValue:t.value.operator,"onUpdate:modelValue":l[14]||(l[14]=e=>t.value.operator=e),options:b.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),p(x).includes("admin-access transactions")?(i(),u("div",Ie,[Je,s(V,{modelValue:t.value.is_binded_customer,"onUpdate:modelValue":l[15]||(l[15]=e=>t.value.is_binded_customer=e),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),o("div",null,[Ue,s(V,{modelValue:t.value.is_payment_received,"onUpdate:modelValue":l[16]||(l[16]=e=>t.value.is_payment_received=e),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),o("div",Ee,[o("div",Ye,[o("div",je,[s(I,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[17]||(l[17]=e=>C())},{default:a(()=>[s(p(W),{class:"h-4 w-4","aria-hidden":"true"}),Ne]),_:1}),s(I,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[18]||(l[18]=e=>G())},{default:a(()=>[s(p(ee),{class:"h-4 w-4","aria-hidden":"true"}),Ae]),_:1}),s(I,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[19]||(l[19]=e=>A())},{default:a(()=>[M.value?f("",!0):(i(),Y(p(te),{key:0,class:"h-4 w-4","aria-hidden":"true"})),M.value?(i(),u("svg",Ge,Re)):f("",!0),ze]),_:1})])]),o("div",He,[o("p",Ze,[qe,o("span",Qe,c((J=d.vendTransactions.meta.from)!=null?J:0),1),We,o("span",Xe,c((U=d.vendTransactions.meta.to)!=null?U:0),1),Te,o("span",et,c(d.vendTransactions.meta.total),1),tt]),s(V,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":l[20]||(l[20]=e=>t.value.numberPerPage=e),options:O.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:C},null,8,["modelValue","options"])])]),o("dl",ot,[o("div",st,[lt,o("dd",at,c(d.totals.amount.toLocaleString(void 0,{minimumFractionDigits:2})),1)]),o("div",nt,[dt,o("dd",rt,c(d.totals.count.toLocaleString(void 0,{minimumFractionDigits:0})),1)])])]),o("div",it,[o("div",ut,[o("div",ct,[o("table",mt,[o("thead",pt,[o("tr",_t,[s(h,null,{default:a(()=>[gt]),_:1}),s(h,null,{default:a(()=>[ht]),_:1}),s(T,{modelName:"transaction_datetime",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:l[21]||(l[21]=e=>K("transaction_datetime"))},{default:a(()=>[ft]),_:1},8,["sortKey","sortBy"]),s(h,null,{default:a(()=>[vt]),_:1}),s(h,null,{default:a(()=>[yt]),_:1}),s(h,null,{default:a(()=>[xt]),_:1}),s(h,null,{default:a(()=>[bt]),_:1}),s(h,null,{default:a(()=>[Vt]),_:1}),s(h,null,{default:a(()=>[Ct]),_:1}),s(h,null,{default:a(()=>[kt]),_:1}),s(h,null,{default:a(()=>[wt]),_:1}),s(h,null,{default:a(()=>[Pt]),_:1})])]),o("tbody",Dt,[(i(!0),u(E,null,H(d.vendTransactions.data,(e,m)=>(i(),u("tr",{key:e.id,class:"divide-x divide-gray-200"},[s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(d.vendTransactions.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.order_id),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.transaction_datetime),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-left"},{default:a(()=>[e.vendJson&&"latest_vend_binding"in e.vendJson&&"customer"in e.vendJson.latest_vend_binding?(i(),u("span",Mt,[r(c(e.vendJson.latest_vend_binding.customer.code)+" ",1),St,r(" "+c(e.vendJson.latest_vend_binding.customer.name),1)])):!e.vendJson&&e.vend.latestVendBinding&&e.vend.latestVendBinding.customer?(i(),u("span",Lt,[r(c(e.vend.latestVendBinding.customer.code)+" ",1),Bt,r(" "+c(e.vend.latestVendBinding.customer.name),1)])):(i(),u("span",Ot,c(e.vend.name),1))]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.vendChannel.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[e.productJson&&"code"in e.productJson?(i(),u("span",$t,c(e.productJson.code),1)):!e.productJson&&e.product&&e.product.code?(i(),u("span",It,c(e.product.code),1)):(i(),u("span",Jt))]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-left"},{default:a(()=>[e.productJson&&"name"in e.productJson?(i(),u("span",Ut,c(e.productJson.name),1)):!e.productJson&&e.product&&e.product.name?(i(),u("span",Et,c(e.product.name),1)):(i(),u("span",Yt))]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-right"},{default:a(()=>[r(c(e.amount),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.paymentMethod?e.paymentMethod.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[r(c(e.vendChannelError?e.vendChannelError.desc:0),1)]),_:2},1032,["currentIndex","totalLength"]),s(_,{currentIndex:m,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:a(()=>[e.is_payment_received?(i(),u("span",jt,c(e.is_payment_received?"Successful":"Unsuccessful"),1)):(i(),u("span",Nt,c(e.vendTransactionJson?e.vendTransactionJson.SErr==0||e.vendTransactionJson.SErr==6?"Successful":"Unsuccessful":""),1))]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vendTransactions.data.length?f("",!0):(i(),u("tr",At,Kt))])]),d.vendTransactions.data.length?(i(),Y(X,{key:0,links:d.vendTransactions.links,meta:d.vendTransactions.meta},null,8,["links","meta"])):f("",!0)])])])])]}),_:1})],64))}};export{so as default};
