import{_ as N}from"./Authenticated.be443d04.js";import{_ as D}from"./Button.39881484.js";import{d as j}from"./main.549c3c9f.js";import{o as p,g as _,d as e,r as F,a as o,b as x,a2 as V,i as h,j as K,w as l,F as I,H,c as O,p as C,t as i,m as J,f as r,a3 as Y,J as $}from"./app.d813bc4f.js";import{_ as E,r as Z,a as z,T as m,b as R,c as q,d as c}from"./TableHeadSort.414fc82d.js";import{_ as v}from"./MultiSelect.6a18b0d9.js";import"./open-closed.e2f9f431.js";import"./use-resolve-button-type.bc3b41a0.js";import"./RectangleStackIcon.328e5432.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.099d189c.js";function Q(d,g){return p(),_("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{d:"M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"}),e("path",{d:"M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"})])}const W={for:"text",class:"block text-sm font-medium text-gray-700"},X={class:"mt-1"},T={__name:"DatePicker",props:{modelValue:[Date,String],minDate:[Date,String],maxDate:[Date,String],enableTimePicker:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(d,{emit:g}){function y(f){g("update:modelValue",V(f).format("Y-M-D"))}return(f,t)=>(p(),_("div",null,[e("label",W,[F(f.$slots,"default")]),e("div",X,[o(x(j),{modelValue:d.modelValue,"onUpdate:modelValue":y,format:"yyyy-MM-dd",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:d.minDate,maxDate:d.maxDate,enableTimePicker:d.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"])])]))}},ee=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (Transactions) ",-1),te={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ae={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"col-span-5 md:col-span-1"},le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Vend ID ",-1),ne={class:"col-span-5 md:col-span-1"},de=r(" From "),re={class:"col-span-5 md:col-span-1"},ie=r(" To "),ue={class:"col-span-5 md:col-span-1"},ce=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),me={class:"col-span-5 md:col-span-1"},pe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Payment Method ",-1),ge={class:"col-span-5 md:col-span-1"},he=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),_e={class:"col-span-5 md:col-span-1"},fe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ve={class:"col-span-5 md:col-span-1"},xe=r(" Cust ID "),ye={class:"col-span-5 md:col-span-1"},be=r(" Cust Name "),Ce={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ve={class:"mt-3"},ke={class:"flex space-x-1"},we=e("span",null," Search ",-1),Pe=e("span",null," Reset ",-1),De={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Me=e("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Se=e("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Be=[Me,Se],Le=e("span",null," Export Excel ",-1),Ie={class:"flex flex-col space-y-2"},Oe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},$e=e("span",null,"Showing",-1),Ee={class:"font-medium"},Te=e("span",null,"to",-1),Ue={class:"font-medium"},Ge=e("span",null,"of",-1),Ae={class:"font-medium"},Ne=e("span",null,"results",-1),je={class:"mt-6 flex flex-col"},Fe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},He={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Je={class:"bg-gray-100"},Ye={class:"divide-x divide-gray-200"},Ze=r(" # "),ze=r(" Order ID "),Re=r(" Transaction DateTime "),qe=r(" Vend ID "),Qe=r(" Customer Name "),We=r(" Channel "),Xe=r(" Amount "),et=r(" Payment Method "),tt=r(" Channels Error "),at=r(" Dispensing Status "),ot={class:"bg-white"},st=e("br",null,null,-1),lt=e("span",null,null,-1),nt={key:0},dt=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),rt=[dt],yt={__name:"Transaction",props:{categories:Object,categoryGroups:Object,paymentMethods:Object,vends:Object,vendTransactions:Object,vendChannelErrors:Object},setup(d){const g=d,y=h([]),f=h([]);K(()=>{M.value=g.vends.data.map(s=>({id:s.id,code:s.code})),S.value=g.vendChannelErrors.data.map(s=>({id:s.id,desc:s.desc})),k.value=[{id:"",name:"All"},...g.paymentMethods.data.map(s=>({id:s.id,name:s.name}))],w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=w.value[0],t.value.paymentMethod=k.value[0],y.value=g.categories.data.map(s=>({id:s.id,name:s.name})),f.value=g.categoryGroups.data.map(s=>({id:s.id,name:s.name}))});const t=h({codes:[],categories:[],categoryGroups:[],customer_code:"",customer_name:"",errors:[],paymentMethod:"",date_from:V().startOf("month").toDate(),date_to:V().toDate(),sortKey:"",sortBy:!1,numberPerPage:100}),M=h([]),S=h([]),b=h(!1),k=h([]),w=h([]);function U(){b.value=!0,Y({method:"get",url:"/vends/transactions/excel",params:{...t.value,categories:t.value.categories.map(s=>s.id),categoryGroups:t.value.categoryGroups.map(s=>s.id),codes:t.value.codes.map(s=>s.id),errors:t.value.errors.map(s=>s.id),paymentMethod:t.value.paymentMethod.id,numberPerPage:t.value.numberPerPage.id},responseType:"blob"}).then(s=>{fileDownload(s.data,"Vending_Transaction_"+V().format("YYMMDDhhmmss")+".xlsx"),b.value=!1})}function P(){$.Inertia.get("/vends/transactions",{...t.value,categories:t.value.categories.map(s=>s.id),categoryGroups:t.value.categoryGroups.map(s=>s.id),codes:t.value.codes.map(s=>s.id),errors:t.value.errors.map(s=>s.id),paymentMethod:t.value.paymentMethod.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function G(){$.Inertia.get("/vends/transactions")}function A(s){t.value.sortKey=s,t.value.sortBy=!t.value.sortBy,P()}return(s,n)=>(p(),_(I,null,[o(x(H),{title:"VM Transactions"}),o(N,null,{header:l(()=>[ee]),default:l(()=>{var B,L;return[e("div",te,[e("div",ae,[e("div",oe,[e("div",se,[le,o(v,{modelValue:t.value.codes,"onUpdate:modelValue":n[0]||(n[0]=a=>t.value.codes=a),options:M.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ne,[o(T,{modelValue:t.value.date_from,"onUpdate:modelValue":n[1]||(n[1]=a=>t.value.date_from=a),class:"col-span-5 md:col-span-1"},{default:l(()=>[de]),_:1},8,["modelValue"])]),e("div",re,[o(T,{modelValue:t.value.date_to,"onUpdate:modelValue":n[2]||(n[2]=a=>t.value.date_to=a),minDate:t.value.date_from,class:"col-span-5 md:col-span-1"},{default:l(()=>[ie]),_:1},8,["modelValue","minDate"])]),e("div",ue,[ce,o(v,{modelValue:t.value.errors,"onUpdate:modelValue":n[3]||(n[3]=a=>t.value.errors=a),options:S.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",me,[pe,o(v,{modelValue:t.value.paymentMethod,"onUpdate:modelValue":n[4]||(n[4]=a=>t.value.paymentMethod=a),options:k.value,valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ge,[he,o(v,{modelValue:t.value.categories,"onUpdate:modelValue":n[5]||(n[5]=a=>t.value.categories=a),options:y.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",_e,[fe,o(v,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":n[6]||(n[6]=a=>t.value.categoryGroups=a),options:f.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ve,[o(E,{placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":n[7]||(n[7]=a=>t.value.customer_code=a)},{default:l(()=>[xe]),_:1},8,["modelValue"])]),e("div",ye,[o(E,{placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":n[8]||(n[8]=a=>t.value.customer_name=a)},{default:l(()=>[be]),_:1},8,["modelValue"])])]),e("div",Ce,[e("div",Ve,[e("div",ke,[o(D,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[9]||(n[9]=a=>P())},{default:l(()=>[o(x(Z),{class:"h-4 w-4","aria-hidden":"true"}),we]),_:1}),o(D,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[10]||(n[10]=a=>G())},{default:l(()=>[o(x(z),{class:"h-4 w-4","aria-hidden":"true"}),Pe]),_:1}),o(D,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[11]||(n[11]=a=>U())},{default:l(()=>[b.value?C("",!0):(p(),O(x(Q),{key:0,class:"h-4 w-4","aria-hidden":"true"})),b.value?(p(),_("svg",De,Be)):C("",!0),Le]),_:1})])]),e("div",Ie,[e("p",Oe,[$e,e("span",Ee,i((B=d.vendTransactions.meta.from)!=null?B:0),1),Te,e("span",Ue,i((L=d.vendTransactions.meta.to)!=null?L:0),1),Ge,e("span",Ae,i(d.vendTransactions.meta.total),1),Ne]),o(v,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":n[12]||(n[12]=a=>t.value.numberPerPage=a),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:P},null,8,["modelValue","options"])])])]),e("div",je,[e("div",Fe,[e("div",Ke,[e("table",He,[e("thead",Je,[e("tr",Ye,[o(m,null,{default:l(()=>[Ze]),_:1}),o(m,null,{default:l(()=>[ze]),_:1}),o(R,{modelName:"transaction_datetime",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:n[13]||(n[13]=a=>A("transaction_datetime"))},{default:l(()=>[Re]),_:1},8,["sortKey","sortBy"]),o(m,null,{default:l(()=>[qe]),_:1}),o(m,null,{default:l(()=>[Qe]),_:1}),o(m,null,{default:l(()=>[We]),_:1}),o(m,null,{default:l(()=>[Xe]),_:1}),o(m,null,{default:l(()=>[et]),_:1}),o(m,null,{default:l(()=>[tt]),_:1}),o(m,null,{default:l(()=>[at]),_:1})])]),e("tbody",ot,[(p(!0),_(I,null,J(d.vendTransactions.data,(a,u)=>(p(),_("tr",{key:a.id,class:"divide-x divide-gray-200"},[o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(d.vendTransactions.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.order_id),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.transaction_datetime),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[r(i(a.vend.latestVendBinding&&a.vend.latestVendBinding.customer?a.vend.latestVendBinding.customer.code:null)+" ",1),st,r(" "+i(a.vend.latestVendBinding&&a.vend.latestVendBinding.customer?a.vend.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.vendChannel.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-right"},{default:l(()=>[r(i(a.amount),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.paymentMethod?a.paymentMethod.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(a.vendChannelError?a.vendChannelError.desc:""),1)]),_:2},1032,["currentIndex","totalLength"]),o(c,{currentIndex:u,totalLength:d.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[lt,r(" "+i(a.vendTransactionJson?a.vendTransactionJson.ISOK==1?"Success":"Failure":""),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vendTransactions.data.length?C("",!0):(p(),_("tr",nt,rt))])]),d.vendTransactions.data.length?(p(),O(q,{key:0,links:d.vendTransactions.links,meta:d.vendTransactions.meta},null,8,["links","meta"])):C("",!0)])])])])]}),_:1})],64))}};export{yt as default};
