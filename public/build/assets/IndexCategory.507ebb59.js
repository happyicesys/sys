import{i as x,l as O,j as G,o as h,g as f,a as n,b as p,w as a,F,H as j,d as e,P as C,c as D,p as v,t as l,m as U,f as r,J as M}from"./app.e7575326.js";import{_ as A}from"./Authenticated.fa9260e4.js";import{_ as T}from"./Button.24cbae9e.js";import{_ as N,r as H,T as b,a as R,b as u}from"./TableData.f525d283.js";import{_ as B}from"./MultiSelect.071347e5.js";import{_ as g}from"./TableHeadSort.6de04c93.js";import{r as E}from"./BackspaceIcon.54d8b46b.js";import"./open-closed.4f96d39d.js";import"./use-resolve-button-type.873c35f6.js";import"./RectangleStackIcon.71092e9f.js";import"./_plugin-vue_export-helper.cdc0426e.js";const J=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by Category ",-1),q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Q={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},W=r(" Vend ID "),X=e("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Y=r(" Cust Name "),Z={key:1},tt=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),et={key:2},ot=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),st=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),nt={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},at={class:"mt-3"},rt={class:"flex space-x-1"},it=e("span",null," Search ",-1),lt=e("span",null," Reset ",-1),dt={class:"flex flex-col space-y-2"},mt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ut=e("span",null,"Showing",-1),ct={class:"font-medium"},gt=e("span",null,"to",-1),_t={class:"font-medium"},ht=e("span",null,"of",-1),ft={class:"font-medium"},pt=e("span",null,"results",-1),yt={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},xt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},vt=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Revenue (This Month)",-1),bt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Bt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Kt=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Profit (This Month)",-1),Lt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},St={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},wt=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Profit Margin (This Month)",-1),$t={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},kt={class:"mt-6 flex flex-col"},Pt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Vt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},Ft={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ct={class:"bg-gray-100"},Dt={class:"divide-x divide-gray-200"},Mt=r(" # "),Tt=r(" Name "),Nt=r(" This Month "),It=r(" Last Month "),Ot=r(" Last 2 Month "),Gt={class:"divide-x divide-gray-200"},jt=r(" Sales($) "),Ut=r(" GM($) "),At=r(" GM(%) "),Ht=r(" Sales($) "),Rt=r(" GM($) "),Et=r(" GM(%) "),Jt=r(" Sales($) "),qt=r(" GM($) "),zt=r(" GM(%) "),Qt={class:"bg-white"},Wt={key:0},Xt=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Yt=[Xt],me={__name:"IndexCategory",props:{categories:Object,monthOptions:Object,operators:Object,totals:[Array,Object],categories:Object},setup(i){const K=i,t=x({categories:[],codes:"",currentMonth:"",customer_name:"",operator_id:"",sortKey:"",sortBy:!0,numberPerPage:100}),k=x([]),L=x([]),S=x([]),w=x([]),$=O().props.value.auth.permissions;G(()=>{w.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=w.value[0],k.value=K.categories.data.map(m=>({id:m.id,name:m.name})),L.value=K.monthOptions.map(m=>({id:m.id,name:m.name})),t.value.currentMonth=L.value[0],S.value=[{id:"all",full_name:"All"},...K.operators.data.map(m=>({id:m.id,full_name:m.full_name}))],t.value.operator_id=S.value[0]});function y(){M.Inertia.get("/reports/category",{...t.value,currentMonth:t.value.currentMonth.id,operator_id:t.value.operator_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){M.Inertia.get("/reports/category")}function c(m){t.value.sortKey=m,t.value.sortBy=!t.value.sortBy,y()}return(m,o)=>(h(),f(F,null,[n(p(j),{title:"GP by Category"}),n(A,null,{header:a(()=>[J]),default:a(()=>{var P,V;return[e("div",q,[e("div",z,[e("div",Q,[n(N,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":o[0]||(o[0]=s=>t.value.codes=s),onKeyup:o[1]||(o[1]=C(s=>y(),["enter"]))},{default:a(()=>[W,X]),_:1},8,["modelValue"]),p($).includes("admin-access products")?(h(),D(N,{key:0,placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":o[2]||(o[2]=s=>t.value.customer_name=s),onKeyup:o[3]||(o[3]=C(s=>y(),["enter"]))},{default:a(()=>[Y]),_:1},8,["modelValue"])):v("",!0),p($).includes("admin-access products")?(h(),f("div",Z,[tt,n(B,{modelValue:t.value.operator_id,"onUpdate:modelValue":o[4]||(o[4]=s=>t.value.operator_id=s),options:S.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):v("",!0),p($).includes("admin-access products")?(h(),f("div",et,[ot,n(B,{modelValue:t.value.categories,"onUpdate:modelValue":o[5]||(o[5]=s=>t.value.categories=s),options:k.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):v("",!0),e("div",null,[st,n(B,{modelValue:t.value.currentMonth,"onUpdate:modelValue":o[6]||(o[6]=s=>t.value.currentMonth=s),options:L.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",nt,[e("div",at,[e("div",rt,[n(T,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[7]||(o[7]=s=>y())},{default:a(()=>[n(p(H),{class:"h-4 w-4","aria-hidden":"true"}),it]),_:1}),n(T,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[8]||(o[8]=s=>I())},{default:a(()=>[n(p(E),{class:"h-4 w-4","aria-hidden":"true"}),lt]),_:1})])]),e("div",dt,[e("p",mt,[ut,e("span",ct,l((P=i.categories.meta.from)!=null?P:0),1),gt,e("span",_t,l((V=i.categories.meta.to)!=null?V:0),1),ht,e("span",ft,l(i.categories.meta.total),1),pt]),n(B,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":o[9]||(o[9]=s=>t.value.numberPerPage=s),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])]),e("dl",yt,[e("div",xt,[vt,e("dd",bt,l(i.totals.revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),e("div",Bt,[Kt,e("dd",Lt,l(i.totals.gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),e("div",St,[wt,e("dd",$t,l(i.totals.gross_profit_margin.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0}))+" % ",1)])])]),e("div",kt,[e("div",Pt,[e("div",Vt,[e("table",Ft,[e("thead",Ct,[e("tr",Dt,[n(b,null,{default:a(()=>[Mt]),_:1}),n(g,{modelName:"name",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[10]||(o[10]=s=>c("name"))},{default:a(()=>[Tt]),_:1},8,["sortKey","sortBy"]),n(b,{colspan:"3"},{default:a(()=>[Nt]),_:1}),n(b,{colspan:"3"},{default:a(()=>[It]),_:1}),n(b,{colspan:"3"},{default:a(()=>[Ot]),_:1})]),e("tr",Gt,[n(b,{colspan:"2"}),n(g,{modelName:"this_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[11]||(o[11]=s=>c("this_month_revenue"))},{default:a(()=>[jt]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"this_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[12]||(o[12]=s=>c("this_month_gross_profit"))},{default:a(()=>[Ut]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"this_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[13]||(o[13]=s=>c("this_month_gross_profit_margin"))},{default:a(()=>[At]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[14]||(o[14]=s=>c("last_month_revenue"))},{default:a(()=>[Ht]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[15]||(o[15]=s=>c("last_month_gross_profit"))},{default:a(()=>[Rt]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[16]||(o[16]=s=>c("last_month_gross_profit_margin"))},{default:a(()=>[Et]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_two_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[17]||(o[17]=s=>c("last_two_month_revenue"))},{default:a(()=>[Jt]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_two_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[18]||(o[18]=s=>c("last_two_month_gross_profit"))},{default:a(()=>[qt]),_:1},8,["sortKey","sortBy"]),n(g,{modelName:"last_two_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:o[19]||(o[19]=s=>c("last_two_month_gross_profit_margin"))},{default:a(()=>[zt]),_:1},8,["sortKey","sortBy"])])]),e("tbody",Qt,[(h(!0),f(F,null,U(i.categories.data,(s,d)=>(h(),f("tr",{key:s.id,class:"divide-x divide-gray-200"},[n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-center"},{default:a(()=>[r(l(i.categories.meta.from+d),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-left"},{default:a(()=>[r(l(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>{var _;return[r(l((_=s.this_month_gross_profit_margin)!=null?_:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>{var _;return[r(l((_=s.last_month_gross_profit_margin)!=null?_:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>[r(l(s.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(u,{currentIndex:d,totalLength:i.categories.length,inputClass:"text-right"},{default:a(()=>{var _;return[r(l((_=s.last_two_month_gross_profit_margin)!=null?_:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),i.categories.data.length?v("",!0):(h(),f("tr",Wt,Yt))])]),i.categories.data.length?(h(),D(R,{key:0,links:i.categories.links,meta:i.categories.meta},null,8,["links","meta"])):v("",!0)])])])])]}),_:1})],64))}};export{me as default};
