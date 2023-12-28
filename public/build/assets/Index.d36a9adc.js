import{g as v,K as j,h as z,f as u,a as s,u as c,w as r,F,o as i,Z as q,b as a,k as L,M as C,d,c as b,l as m,t as p,O as U,i as H,n as J}from"./app.4ea561de.js";import{_ as Q}from"./Authenticated.fc486f5c.js";import{_ as N}from"./Button.186f76df.js";import{_ as A}from"./DatePicker.7291c5d8.js";import{_ as W}from"./Paginator.f8fa0454.js";import{_ as k,r as X}from"./SearchInput.96c0b981.js";import{_}from"./MultiSelect.718cb95e.js";import{_ as T,a as y}from"./TableData.e074782f.js";import{_ as I}from"./TableHeadSort.b6c4de55.js";import{r as ee}from"./BackspaceIcon.f410e87c.js";import{r as te}from"./ArrowDownTrayIcon.1013e42b.js";import"./open-closed.9b3740ac.js";import"./use-resolve-button-type.a595af34.js";import"./RectangleStackIcon.bf7bcecf.js";import"./main.47bde298.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.8a8cc677.js";const oe=a("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Sales Report ",-1),ae={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},le={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},se={class:"pb-3 mb-2"},ne={class:"sm:hidden"},re=a("label",{for:"tabs",class:"sr-only"},"Select a tab",-1),ie=["value","selected"],de={class:"hidden sm:block"},ue={class:"border-b border-gray-200"},me={class:"-mb-px flex space-x-8","aria-label":"Tabs"},ce={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},pe=a("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ve={key:2},fe=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),ge={key:3},_e=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),ye={key:4},xe=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),be={key:5},he=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Ve=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),Ce={key:6},ke=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Filter Date ",-1),De={key:7},we={key:8},Se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Fe={class:"mt-3"},Pe={class:"flex space-x-1"},Be=a("span",null," Search ",-1),Oe=a("span",null," Reset ",-1),$e={class:"flex space-x-1"},Ke={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Le=a("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Ue=a("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ne=[Le,Ue],Te=a("span",null," Export Excel ",-1),Ie={class:"flex flex-col space-y-2"},Ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Me=a("span",null,"Showing",-1),je={class:"font-medium"},Ae=a("span",null,"to",-1),Re={class:"font-medium"},Ee=a("span",null,"of",-1),Ze={class:"font-medium"},Ye=a("span",null,"results",-1),ze={class:"mt-6 flex flex-col"},qe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},He={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},Je={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Qe={class:"bg-gray-100"},We={class:"divide-x divide-gray-200"},Xe={class:"divide-x divide-gray-200"},et={class:"bg-white"},tt={key:0},ot=a("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),at=[ot],bt={__name:"Index",props:{categories:Object,categoryGroups:Object,items:Object,locationTypeOptions:Object,reportDateOptions:Object,operators:Object,totals:[Array,Object]},setup(n){const h=n,e=v({categories:[],categoryGroups:[],codes:"",currentFilterDate:"",customer_code:"",customer_name:"",date_from:"",date_to:"",is_binded_customer:"",location_type_id:"",operator_id:"",product_code:"",product_name:"",sortKey:"",sortBy:!1,numberPerPage:30,visited:!1}),D=v([]),G=v([]),M=v([]),w=v(!1),P=v([]),V=v([]),B=v([]),R=j().props.auth.operatorRole,O=v([]),g=j().props.auth.permissions,S=v(),$=v([{name:"Operator",href:"/reports/sales/operator",current:!1},{name:"Vending Machines",href:"/reports/sales/vend",current:!1},{name:"Product",href:"/reports/sales/product",current:!1},{name:"Category",href:"/reports/sales/category",current:!1},{name:"Location Type",href:"/reports/sales/location-type",current:!1}]);z(()=>{S.value=window.location.pathname,$.value.find(l=>l.href===window.location.pathname).current=!0,e.value.visited=!0,O.value=[{id:30,value:30},{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],e.value.numberPerPage=O.value[0],D.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],G.value=h.categories.data.map(l=>({id:l.id,name:l.name})),M.value=h.categoryGroups.data.map(l=>({id:l.id,name:l.name})),P.value=[{id:"all",value:"All"},...h.locationTypeOptions.data.map(l=>({id:l.id,value:l.name}))],V.value=h.reportDateOptions.map(l=>({id:l.id,name:l.name})),V.value=[...V.value,{id:"-1",name:"Custom Date"}],e.value.currentFilterDate=V.value[0],B.value=[{id:"all",full_name:"All"},...h.operators.data.map(l=>({id:l.id,full_name:l.full_name}))],e.value.is_binded_customer=R.value?D.value[0]:D.value[1],e.value.location_type_id=P.value[0],e.value.operator_id=B.value[0]});function f(){U.get(S.value,{...e.value,categories:e.value.categories.map(l=>l.id),categoryGroups:e.value.categoryGroups.map(l=>l.id),currentFilterDate:e.value.currentFilterDate.id,is_binded_customer:e.value.is_binded_customer.id,location_type_id:e.value.location_type_id.id,operator_id:e.value.operator_id.id,numberPerPage:e.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(l){U.get(l.target.value)}function Z(){U.get(S.value)}function K(l){e.value.sortKey=l,e.value.sortBy=!e.value.sortBy,f()}function Y(){w.value=!0,axios({method:"get",url:S.value+"/excel",params:{...e.value,categories:e.value.categories.map(l=>l.id),categoryGroups:e.value.categoryGroups.map(l=>l.id),currentFilterDate:e.value.currentFilterDate.id,is_binded_customer:e.value.is_binded_customer.id,location_type_id:e.value.location_type_id.id,operator_id:e.value.operator_id.id},responseType:"blob"}).then(l=>{fileDownload(l.data,"SalesReport"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(l=>{console.log(l)}).finally(()=>{w.value=!1})}return(l,o)=>(i(),u(F,null,[s(c(q),{title:"GP by VM"}),s(Q,null,{header:r(()=>[oe]),default:r(()=>[a("div",ae,[a("div",le,[a("div",se,[a("div",ne,[re,a("select",{id:"tabs",name:"tabs",class:"block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm",onChange:o[0]||(o[0]=t=>E(t))},[(i(!0),u(F,null,L($.value,t=>(i(),u("option",{key:t,value:t.href,selected:t.current},p(t.name),9,ie))),128))],32)]),a("div",de,[a("div",ue,[a("nav",me,[(i(!0),u(F,null,L($.value,t=>(i(),b(c(H),{key:t.name,class:J([t.current?"border-indigo-500 text-indigo-600":"border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-700","flex whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"]),href:t.href},{default:r(()=>[d(p(t.name),1)]),_:2},1032,["class","href"]))),128))])])])]),a("div",ce,[s(k,{placeholderStr:"Vend ID",modelValue:e.value.codes,"onUpdate:modelValue":o[1]||(o[1]=t=>e.value.codes=t),onKeyup:o[2]||(o[2]=C(t=>f(),["enter"]))},{default:r(()=>[d(" Vend ID "),pe]),_:1},8,["modelValue"]),c(g).includes("admin-access vends")?(i(),b(k,{key:0,placeholderStr:"Cust ID",modelValue:e.value.customer_code,"onUpdate:modelValue":o[3]||(o[3]=t=>e.value.customer_code=t),onKeyup:o[4]||(o[4]=C(t=>f(),["enter"]))},{default:r(()=>[d(" Cust ID ")]),_:1},8,["modelValue"])):m("",!0),c(g).includes("admin-access vends")?(i(),b(k,{key:1,placeholderStr:"Cust Name",modelValue:e.value.customer_name,"onUpdate:modelValue":o[5]||(o[5]=t=>e.value.customer_name=t),onKeyup:o[6]||(o[6]=C(t=>f(),["enter"]))},{default:r(()=>[d(" Cust Name ")]),_:1},8,["modelValue"])):m("",!0),s(k,{placeholderStr:"Product ID",modelValue:e.value.product_code,"onUpdate:modelValue":o[7]||(o[7]=t=>e.value.product_code=t),onKeyup:o[8]||(o[8]=C(t=>f(),["enter"]))},{default:r(()=>[d(" Product ID ")]),_:1},8,["modelValue"]),s(k,{placeholderStr:"Product Name",modelValue:e.value.product_name,"onUpdate:modelValue":o[9]||(o[9]=t=>e.value.product_name=t),onKeyup:o[10]||(o[10]=C(t=>f(),["enter"]))},{default:r(()=>[d(" Product Name ")]),_:1},8,["modelValue"]),c(g).includes("admin-access vends")?(i(),u("div",ve,[fe,s(_,{modelValue:e.value.is_binded_customer,"onUpdate:modelValue":o[11]||(o[11]=t=>e.value.is_binded_customer=t),options:D.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),c(g).includes("admin-access vends")?(i(),u("div",ge,[_e,s(_,{modelValue:e.value.operator_id,"onUpdate:modelValue":o[12]||(o[12]=t=>e.value.operator_id=t),options:B.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),c(g).includes("admin-access vends")?(i(),u("div",ye,[xe,s(_,{modelValue:e.value.categories,"onUpdate:modelValue":o[13]||(o[13]=t=>e.value.categories=t),options:G.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),c(g).includes("admin-access vends")?(i(),u("div",be,[he,s(_,{modelValue:e.value.categoryGroups,"onUpdate:modelValue":o[14]||(o[14]=t=>e.value.categoryGroups=t),options:M.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),a("div",null,[Ve,s(_,{modelValue:e.value.location_type_id,"onUpdate:modelValue":o[15]||(o[15]=t=>e.value.location_type_id=t),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e.value.currentFilterDate.id!="-1"?(i(),u("div",Ce,[ke,s(_,{modelValue:e.value.currentFilterDate,"onUpdate:modelValue":o[16]||(o[16]=t=>e.value.currentFilterDate=t),options:V.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),e.value.currentFilterDate.id=="-1"?(i(),u("div",De,[s(A,{modelValue:e.value.date_from,"onUpdate:modelValue":o[17]||(o[17]=t=>e.value.date_from=t)},{default:r(()=>[d(" Date From ")]),_:1},8,["modelValue"])])):m("",!0),e.value.currentFilterDate.id=="-1"?(i(),u("div",we,[s(A,{modelValue:e.value.date_to,"onUpdate:modelValue":o[18]||(o[18]=t=>e.value.date_to=t),minDate:e.value.date_from},{default:r(()=>[d(" Date To ")]),_:1},8,["modelValue","minDate"])])):m("",!0)]),a("div",Se,[a("div",Fe,[a("div",Pe,[s(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[19]||(o[19]=t=>f())},{default:r(()=>[s(c(X),{class:"h-4 w-4","aria-hidden":"true"}),Be]),_:1}),s(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[20]||(o[20]=t=>Z())},{default:r(()=>[s(c(ee),{class:"h-4 w-4","aria-hidden":"true"}),Oe]),_:1}),c(g).includes("export excel")?(i(),b(N,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:o[21]||(o[21]=t=>Y())},{default:r(()=>[a("div",$e,[a("div",null,[w.value?m("",!0):(i(),b(c(te),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(i(),u("svg",Ke,Ne)):m("",!0)]),Te])]),_:1})):m("",!0)])]),a("div",Ie,[a("p",Ge,[Me,a("span",je,p(n.items&&n.items.meta&&n.items.meta.from?n.items.meta.from:0),1),Ae,a("span",Re,p(n.items&&n.items.meta&&n.items.meta.to?n.items.meta.to:0),1),Ee,a("span",Ze,p(n.items&&n.items.meta&&n.items.meta.total?n.items.meta.total:0),1),Ye]),s(_,{modelValue:e.value.numberPerPage,"onUpdate:modelValue":o[22]||(o[22]=t=>e.value.numberPerPage=t),options:O.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:f},null,8,["modelValue","options"])])])]),a("div",ze,[a("div",qe,[a("div",He,[a("table",Je,[a("thead",Qe,[a("tr",We,[s(T,null,{default:r(()=>[d(" # ")]),_:1}),s(I,{modelName:"code",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:o[23]||(o[23]=t=>K("code"))},{default:r(()=>[d(" ID ")]),_:1},8,["sortKey","sortBy"]),s(T,null,{default:r(()=>[d(" Name ")]),_:1}),s(I,{modelName:"count",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:o[24]||(o[24]=t=>K("count"))},{default:r(()=>[d(" Count ")]),_:1},8,["sortKey","sortBy"]),s(I,{modelName:"amount",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:o[25]||(o[25]=t=>K("amount"))},{default:r(()=>[d(" Amount ")]),_:1},8,["sortKey","sortBy"])]),a("tr",Xe,[s(T,{colspan:"3"}),s(y,{inputClass:"text-right font-semibold"},{default:r(()=>[d(p(n.totals.total_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),s(y,{inputClass:"text-right font-semibold"},{default:r(()=>[d(p(n.totals.total_amount.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1})])]),a("tbody",et,[(i(!0),u(F,null,L(n.items.data,(t,x)=>(i(),u("tr",{key:t.id,class:"divide-x divide-gray-200"},[s(y,{currentIndex:x,totalLength:n.items.length,inputClass:"text-center"},{default:r(()=>[d(p(n.items.meta.from+x),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:x,totalLength:n.items.length,inputClass:"text-center"},{default:r(()=>[d(p(t.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:x,totalLength:n.items.length,inputClass:"text-left"},{default:r(()=>[d(p(t.name),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:x,totalLength:n.items.length,inputClass:"text-right"},{default:r(()=>[d(p(t.count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:x,totalLength:n.items.length,inputClass:"text-right"},{default:r(()=>[d(p(t.amount.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.items.data.length?m("",!0):(i(),u("tr",tt,at))])]),n.items.data.length?(i(),b(W,{key:0,links:n.items.links,meta:n.items.meta},null,8,["links","meta"])):m("",!0)])])])])]),_:1})],64))}};export{bt as default};
