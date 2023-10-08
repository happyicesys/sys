import{g as m,K as V,h as M,f as p,a as o,u as d,w as l,F as U,o as u,Z as R,b as e,i as j,M as L,d as r,c as S,l as f,t as c,k as E,O as D,n as H}from"./app.009697ae.js";import{_ as z}from"./Authenticated.fb2385b7.js";import{_ as w}from"./Button.37f68050.js";import{_ as Y}from"./Paginator.e935ece5.js";import{_ as $,r as Z}from"./SearchInput.284e54ad.js";import{_ as y}from"./MultiSelect.418dfc00.js";import{_,a as v}from"./TableData.ad1262ee.js";import{_ as q}from"./TableHeadSort.957a93c4.js";import{r as J}from"./PlusIcon.58a26d2c.js";import{r as Q}from"./BackspaceIcon.65987218.js";import{r as W}from"./PencilSquareIcon.8cc79a9c.js";import"./open-closed.0a17ce87.js";import"./use-resolve-button-type.af25536c.js";import"./RectangleStackIcon.00e2746e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dbddf1f4.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Device Management ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},te={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},se={class:"flex justify-end"},oe=e("span",null," Create ",-1),ae={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},le=e("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ne={key:2},ie=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),re={key:3},de=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ue={key:4},ce=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Active? ",-1),me={key:5},pe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),ge=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),fe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ve={class:"mt-3"},_e={class:"flex space-x-1"},xe=e("span",null," Search ",-1),he=e("span",null," Reset ",-1),ye={class:"flex flex-col space-y-2"},be={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ve=e("span",null,"Showing",-1),ke={class:"font-medium"},Be=e("span",null,"to",-1),we={class:"font-medium"},Ce=e("span",null,"of",-1),Oe={class:"font-medium"},Pe=e("span",null,"results",-1),Le={class:"mt-6 flex flex-col"},Se={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},$e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ie={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Te={class:"bg-gray-100"},Ke={class:"divide-x divide-gray-200"},Ne={class:"bg-white"},Ue={key:0},je=["href"],De=e("br",null,null,-1),Ge={key:1},Ae={class:"flex flex-col space-y-1"},Fe={class:"flex flex-col"},Me={class:"font-bold"},Re={class:"flex justify-center space-x-1"},Ee=e("span",null," Edit ",-1),He={key:0},ze=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ye=[ze],dt={__name:"Index",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,operatorOptions:Object,vends:Object},setup(n){const k=n,s=m({codes:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],locationType:"",operator:"",is_active:"",is_binded_customer:"",sortKey:"",sortBy:!0,numberPerPage:"",visited:!0}),b=m([]),I=m([]),T=m([]),G=V().props.initBinded;m(!1);const C=m([]),O=m([]),P=m([]);m(""),m(),V().props.auth.operatorCountry,V().props.auth.operatorRole;const x=V().props.auth.permissions,B=V().props.auth.roles;m(moment().format("HH:mm:ss")),M(()=>{O.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=O.value[0],I.value=k.categories.data.map(i=>({id:i.id,name:i.name})),T.value=k.categoryGroups.data.map(i=>({id:i.id,name:i.name})),b.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],C.value=[{id:"all",value:"All"},...k.locationTypeOptions.data.map(i=>({id:i.id,value:i.name}))],P.value=[{id:"all",full_name:"All"},...k.operatorOptions.data.map(i=>({id:i.id,full_name:i.full_name}))],s.value.locationType=C.value[0],s.value.operator=P.value[0],s.value.is_active=b.value[1],s.value.is_binded_customer=G&&(B[0]=="superadmin"||B[0]=="admin"||B[0]=="supervisor"||B[0]=="driver")?b.value[1]:b.value[0]});function h(){D.get("/settings",{...s.value,categories:s.value.categories.map(i=>i.id),categoryGroups:s.value.categoryGroups.map(i=>i.id),location_type_id:s.value.locationType.id,operator_id:s.value.operator.id,is_active:s.value.is_active.id,is_binded_customer:s.value.is_binded_customer.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function A(){D.get("/settings")}function F(i){s.value.sortKey=i,s.value.sortBy=!s.value.sortBy,h()}return(i,a)=>(u(),p(U,null,[o(d(R),{title:"VM Management"}),o(z,null,{header:l(()=>[X]),default:l(()=>{var K,N;return[e("div",ee,[e("div",te,[e("div",se,[o(d(j),{href:"/settings/vend/0/create"},{default:l(()=>[o(w,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:l(()=>[o(d(J),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1})]),_:1})]),e("div",ae,[o($,{placeholderStr:"Vend ID",modelValue:s.value.codes,"onUpdate:modelValue":a[0]||(a[0]=t=>s.value.codes=t),onKeyup:a[1]||(a[1]=L(t=>h(),["enter"]))},{default:l(()=>[r(" Vend ID "),le]),_:1},8,["modelValue"]),d(x).includes("admin-access vends")?(u(),S($,{key:0,placeholderStr:"Cust ID",modelValue:s.value.customer_code,"onUpdate:modelValue":a[2]||(a[2]=t=>s.value.customer_code=t),onKeyup:a[3]||(a[3]=L(t=>h(),["enter"]))},{default:l(()=>[r(" Cust ID ")]),_:1},8,["modelValue"])):f("",!0),d(x).includes("admin-access vends")?(u(),S($,{key:1,placeholderStr:"Cust Name",modelValue:s.value.customer_name,"onUpdate:modelValue":a[4]||(a[4]=t=>s.value.customer_name=t),onKeyup:a[5]||(a[5]=L(t=>h(),["enter"]))},{default:l(()=>[r(" Cust Name ")]),_:1},8,["modelValue"])):f("",!0),d(x).includes("admin-access vends")?(u(),p("div",ne,[ie,o(y,{modelValue:s.value.categories,"onUpdate:modelValue":a[6]||(a[6]=t=>s.value.categories=t),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),d(x).includes("admin-access vends")?(u(),p("div",re,[de,o(y,{modelValue:s.value.categoryGroups,"onUpdate:modelValue":a[7]||(a[7]=t=>s.value.categoryGroups=t),options:T.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),d(x).includes("admin-access vends")?(u(),p("div",ue,[ce,o(y,{modelValue:s.value.is_active,"onUpdate:modelValue":a[8]||(a[8]=t=>s.value.is_active=t),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),d(x).includes("admin-access vends")?(u(),p("div",me,[pe,o(y,{modelValue:s.value.operator,"onUpdate:modelValue":a[9]||(a[9]=t=>s.value.operator=t),options:P.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):f("",!0),e("div",null,[ge,o(y,{modelValue:s.value.locationType,"onUpdate:modelValue":a[10]||(a[10]=t=>s.value.locationType=t),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",fe,[e("div",ve,[e("div",_e,[o(w,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[11]||(a[11]=t=>h())},{default:l(()=>[o(d(Z),{class:"h-4 w-4","aria-hidden":"true"}),xe]),_:1}),o(w,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[12]||(a[12]=t=>A())},{default:l(()=>[o(d(Q),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1})])]),e("div",ye,[e("p",be,[Ve,e("span",ke,c((K=n.vends.meta.from)!=null?K:0),1),Be,e("span",we,c((N=n.vends.meta.to)!=null?N:0),1),Ce,e("span",Oe,c(n.vends.meta.total),1),Pe]),o(y,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":a[13]||(a[13]=t=>s.value.numberPerPage=t),options:O.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:h},null,8,["modelValue","options"])])])]),e("div",Le,[e("div",Se,[e("div",$e,[e("table",Ie,[e("thead",Te,[e("tr",Ke,[o(_,null,{default:l(()=>[r(" # ")]),_:1}),o(q,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[14]||(a[14]=t=>F("code"))},{default:l(()=>[r(" Code ")]),_:1},8,["sortKey","sortBy"]),o(_,null,{default:l(()=>[r(" Name ")]),_:1}),o(_,null,{default:l(()=>[r(" Status ")]),_:1}),o(_,null,{default:l(()=>[r(" Begin Date ")]),_:1}),o(_,null,{default:l(()=>[r(" Deactivation Date ")]),_:1}),o(_,null,{default:l(()=>[r(" Operator ")]),_:1}),o(_)])]),e("tbody",Ne,[(u(!0),p(U,null,E(n.vends.data,(t,g)=>(u(),p("tr",{key:t.id,class:"divide-x divide-gray-200"},[o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[r(c(n.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-left"},{default:l(()=>[r(c(t.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-left"},{default:l(()=>[t.latestVendBinding&&t.latestVendBinding.customer?(u(),p("span",Ue,[e("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/"+t.latestVendBinding.customer.person_id+"/edit"},[r(c(t.latestVendBinding.customer.code)+" ",1),De,r(" "+c(t.latestVendBinding.customer.name),1)],8,je)])):(u(),p("span",Ge,c(t.name),1))]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[e("div",Ae,[e("div",{class:H(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[t.is_active?"bg-green-200":"bg-red-200"]])},[e("div",Fe,[e("span",Me,c(t.is_active?"Active":"Inactive"),1)])],2)])]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[r(c(t.begin_date_short),1)]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[r(c(t.termination_date_short),1)]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[r(c(t.latestOperator?t.latestOperator.full_name:""),1)]),_:2},1032,["currentIndex","totalLength"]),o(v,{currentIndex:g,totalLength:n.vends.length,inputClass:"text-center"},{default:l(()=>[e("div",Re,[o(d(j),{href:"/settings/vend/"+t.id+"/update"},{default:l(()=>[o(w,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"},{default:l(()=>[o(d(W),{class:"w-4 h-4"}),Ee]),_:1})]),_:2},1032,["href"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.vends.data.length?f("",!0):(u(),p("tr",He,Ye))])]),n.vends.data.length?(u(),S(Y,{key:0,links:n.vends.links,meta:n.vends.meta},null,8,["links","meta"])):f("",!0)])])])])]}),_:1})],64))}};export{dt as default};
