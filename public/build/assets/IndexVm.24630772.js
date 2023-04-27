import{i as f,l as O,j as H,o as u,g as c,a as n,b as g,w as a,F as U,H as Y,d as s,P as D,c as S,p as h,t as d,m as Z,f as l,J as j}from"./app.ebff5b87.js";import{_ as J}from"./Authenticated.b8823568.js";import{_ as P}from"./Button.adeb3e4b.js";import{_ as T,r as q,T as C,a as z,b as _}from"./TableData.30f7e0b2.js";import{_ as b}from"./MultiSelect.5ad8ee17.js";import{_ as v}from"./TableHeadSort.edbf866f.js";import{r as Q}from"./BackspaceIcon.e922c2fb.js";import{r as W}from"./ArrowDownTrayIcon.95ab6a66.js";import"./open-closed.d9ea9bdd.js";import"./use-resolve-button-type.12a7e655.js";import"./RectangleStackIcon.65279a46.js";import"./_plugin-vue_export-helper.cdc0426e.js";const X=s("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by VM ",-1),tt={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},et={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ot={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},st=l(" Vend ID "),nt=s("span",{class:"text-[9px]"},' ("," for multiple) ',-1),at=l(" Cust ID "),lt=l(" Cust Name "),rt={key:2},it=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),dt={key:3},ut=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),mt={key:4},ct=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),_t={key:5},gt=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),pt=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),ht=s("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),vt={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ft={class:"mt-3"},yt={class:"flex space-x-1"},xt=s("span",null," Search ",-1),bt=s("span",null," Reset ",-1),Bt={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ct=s("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Vt=s("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),kt=[Ct,Vt],wt=s("span",null," Export Excel ",-1),St={class:"flex flex-col space-y-2"},Lt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Kt=s("span",null,"Showing",-1),$t={class:"font-medium"},Mt=s("span",null,"to",-1),Dt={class:"font-medium"},Pt=s("span",null,"of",-1),Tt={class:"font-medium"},Ft=s("span",null,"results",-1),Gt={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},It={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Nt=s("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Revenue (This Month)",-1),Ot={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ut={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},jt=s("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Profit (This Month)",-1),At={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Et={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Rt=s("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Profit Margin (This Month)",-1),Ht={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Yt={class:"mt-6 flex flex-col"},Zt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Jt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},qt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},zt={class:"bg-gray-100"},Qt={class:"divide-x divide-gray-200"},Wt=l(" # "),Xt=l(" ID "),te=l(" Name "),ee=l(" This Month "),oe=l(" Last Month "),se=l(" Last 2 Month "),ne={class:"divide-x divide-gray-200"},ae=l(" Sales($) "),le=l(" GM($) "),re=l(" GM(%) "),ie=l(" Sales($) "),de=l(" GM($) "),ue=l(" GM(%) "),me=l(" Sales($) "),ce=l(" GM($) "),_e=l(" GM(%) "),ge={class:"bg-white"},pe={key:0},he={key:0},ve=s("br",null,null,-1),fe={key:1},ye=s("br",null,null,-1),xe={key:1},be={key:0},Be=s("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ce=[Be],Ge={__name:"IndexVm",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,monthOptions:Object,operators:Object,totals:[Array,Object],vends:Object},setup(i){const V=i,t=f({categories:[],categoryGroups:[],codes:"",currentMonth:"",customer_code:"",customer_name:"",is_binded_customer:"",location_type_id:"",operator_id:"",sortKey:"",sortBy:!1,numberPerPage:100}),k=f([]),F=f([]),G=f([]),w=f(!1),L=f([]),K=f([]),$=f([]),A=O().props.value.auth.operatorRole,M=f([]),x=O().props.value.auth.permissions;H(()=>{M.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=M.value[0],k.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],F.value=V.categories.data.map(r=>({id:r.id,name:r.name})),G.value=V.categoryGroups.data.map(r=>({id:r.id,name:r.name})),L.value=[{id:"all",value:"All"},...V.locationTypeOptions.data.map(r=>({id:r.id,value:r.name}))],K.value=V.monthOptions.map(r=>({id:r.id,name:r.name})),t.value.currentMonth=K.value[0],$.value=[{id:"all",full_name:"All"},...V.operators.data.map(r=>({id:r.id,full_name:r.full_name}))],t.value.is_binded_customer=A.value?k.value[0]:k.value[1],t.value.location_type_id=L.value[0],t.value.operator_id=$.value[0]});function B(){j.Inertia.get("/reports/vend",{...t.value,categories:t.value.categories.map(r=>r.id),categoryGroups:t.value.categoryGroups.map(r=>r.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){j.Inertia.get("/reports/vend")}function p(r){t.value.sortKey=r,t.value.sortBy=!t.value.sortBy,B()}function R(){w.value=!0,axios({method:"get",url:"/reports/vend/excel",params:{...t.value,categories:t.value.categories.map(r=>r.id),categoryGroups:t.value.categoryGroups.map(r=>r.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id},responseType:"blob"}).then(r=>{fileDownload(r.data,"UnitCostByVm_"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(r=>{console.log(r)}).finally(()=>{w.value=!1})}return(r,o)=>(u(),c(U,null,[n(g(Y),{title:"GP by VM"}),n(J,null,{header:a(()=>[X]),default:a(()=>{var I,N;return[s("div",tt,[s("div",et,[s("div",ot,[n(T,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":o[0]||(o[0]=e=>t.value.codes=e),onKeyup:o[1]||(o[1]=D(e=>B(),["enter"]))},{default:a(()=>[st,nt]),_:1},8,["modelValue"]),g(x).includes("admin-access vends")?(u(),S(T,{key:0,placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":o[2]||(o[2]=e=>t.value.customer_code=e),onKeyup:o[3]||(o[3]=D(e=>B(),["enter"]))},{default:a(()=>[at]),_:1},8,["modelValue"])):h("",!0),g(x).includes("admin-access vends")?(u(),S(T,{key:1,placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":o[4]||(o[4]=e=>t.value.customer_name=e),onKeyup:o[5]||(o[5]=D(e=>B(),["enter"]))},{default:a(()=>[lt]),_:1},8,["modelValue"])):h("",!0),g(x).includes("admin-access vends")?(u(),c("div",rt,[it,n(b,{modelValue:t.value.is_binded_customer,"onUpdate:modelValue":o[6]||(o[6]=e=>t.value.is_binded_customer=e),options:k.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),g(x).includes("admin-access vends")?(u(),c("div",dt,[ut,n(b,{modelValue:t.value.operator_id,"onUpdate:modelValue":o[7]||(o[7]=e=>t.value.operator_id=e),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),g(x).includes("admin-access vends")?(u(),c("div",mt,[ct,n(b,{modelValue:t.value.categories,"onUpdate:modelValue":o[8]||(o[8]=e=>t.value.categories=e),options:F.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),g(x).includes("admin-access vends")?(u(),c("div",_t,[gt,n(b,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":o[9]||(o[9]=e=>t.value.categoryGroups=e),options:G.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),s("div",null,[pt,n(b,{modelValue:t.value.location_type_id,"onUpdate:modelValue":o[10]||(o[10]=e=>t.value.location_type_id=e),options:L.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s("div",null,[ht,n(b,{modelValue:t.value.currentMonth,"onUpdate:modelValue":o[11]||(o[11]=e=>t.value.currentMonth=e),options:K.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),s("div",vt,[s("div",ft,[s("div",yt,[n(P,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[12]||(o[12]=e=>B())},{default:a(()=>[n(g(q),{class:"h-4 w-4","aria-hidden":"true"}),xt]),_:1}),n(P,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[13]||(o[13]=e=>E())},{default:a(()=>[n(g(Q),{class:"h-4 w-4","aria-hidden":"true"}),bt]),_:1}),n(P,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[14]||(o[14]=e=>R())},{default:a(()=>[w.value?h("",!0):(u(),S(g(W),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(u(),c("svg",Bt,kt)):h("",!0),wt]),_:1})])]),s("div",St,[s("p",Lt,[Kt,s("span",$t,d((I=i.vends.meta.from)!=null?I:0),1),Mt,s("span",Dt,d((N=i.vends.meta.to)!=null?N:0),1),Pt,s("span",Tt,d(i.vends.meta.total),1),Ft]),n(b,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":o[15]||(o[15]=e=>t.value.numberPerPage=e),options:M.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:B},null,8,["modelValue","options"])])]),s("dl",Gt,[s("div",It,[Nt,s("dd",Ot,d(i.totals.revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),s("div",Ut,[jt,s("dd",At,d(i.totals.gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),s("div",Et,[Rt,s("dd",Ht,d(i.totals.gross_profit_margin.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0}))+" % ",1)])])]),s("div",Yt,[s("div",Zt,[s("div",Jt,[s("table",qt,[s("thead",zt,[s("tr",Qt,[n(C,null,{default:a(()=>[Wt]),_:1}),n(C,null,{default:a(()=>[Xt]),_:1}),n(v,{modelName:"name",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[16]||(o[16]=e=>p("name"))},{default:a(()=>[te]),_:1},8,["sortKey","sortBy"]),n(C,{colspan:"3"},{default:a(()=>[ee]),_:1}),n(C,{colspan:"3"},{default:a(()=>[oe]),_:1}),n(C,{colspan:"3"},{default:a(()=>[se]),_:1})]),s("tr",ne,[n(C,{colspan:"3"}),n(v,{modelName:"this_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[17]||(o[17]=e=>p("this_month_revenue"))},{default:a(()=>[ae]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"this_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[18]||(o[18]=e=>p("this_month_gross_profit"))},{default:a(()=>[le]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"this_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[19]||(o[19]=e=>p("this_month_gross_profit_margin"))},{default:a(()=>[re]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[20]||(o[20]=e=>p("last_month_revenue"))},{default:a(()=>[ie]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[21]||(o[21]=e=>p("last_month_gross_profit"))},{default:a(()=>[de]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[22]||(o[22]=e=>p("last_month_gross_profit_margin"))},{default:a(()=>[ue]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_two_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[23]||(o[23]=e=>p("last_two_month_revenue"))},{default:a(()=>[me]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_two_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[24]||(o[24]=e=>p("last_two_month_gross_profit"))},{default:a(()=>[ce]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"last_two_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[25]||(o[25]=e=>p("last_two_month_gross_profit_margin"))},{default:a(()=>[_e]),_:1},8,["sortKey","sortBy"])])]),s("tbody",ge,[(u(!0),c(U,null,Z(i.vends.data,(e,m)=>(u(),c("tr",{key:e.id,class:"divide-x divide-gray-200"},[n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:a(()=>[l(d(i.vends.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:a(()=>[l(d(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-left"},{default:a(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(u(),c("span",pe,[g(x).includes("admin-access vends")?(u(),c("span",he,[l(d(e.latestVendBinding.customer.code)+" ",1),ve,l(" "+d(e.latestVendBinding.customer.name),1)])):(u(),c("span",fe,[l(d(e.latestVendBinding.customer.code)+" ",1),ye,l(" "+d(e.latestVendBinding.customer.name),1)]))])):(u(),c("span",xe,d(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>{var y;return[l(d((y=e.this_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>{var y;return[l(d((y=e.last_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>[l(d(e.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(_,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-right"},{default:a(()=>{var y;return[l(d((y=e.last_two_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),i.vends.data.length?h("",!0):(u(),c("tr",be,Ce))])]),i.vends.data.length?(u(),S(z,{key:0,links:i.vends.links,meta:i.vends.meta},null,8,["links","meta"])):h("",!0)])])])])]}),_:1})],64))}};export{Ge as default};
