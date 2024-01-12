import{_ as D}from"./Authenticated.3a544bed.js";import{_ as v}from"./Button.be945d63.js";import R from"./Form.86a8bffd.js";import{_ as Q}from"./Paginator.d94b1d1a.js";import{_ as O,r as Z}from"./SearchInput.af49f9a9.js";import{_ as N}from"./MultiSelect.fb25c41f.js";import{_,a as f}from"./TableData.e7cfe68e.js";import{_ as j}from"./TableHeadSort.5d066413.js";import{g,Q as q,h as z,f as y,a as t,u as d,w as o,F as I,o as u,Z as G,b as e,c as b,l as p,d as r,t as m,k as H,O as k}from"./app.242e5fba.js";import{r as J}from"./PlusIcon.106d5b37.js";import{r as W}from"./BackspaceIcon.dce1ca77.js";import{r as X}from"./PencilSquareIcon.47aec97a.js";import{r as Y}from"./TrashIcon.4a89c528.js";import"./keyboard.034c1cc1.js";import"./use-resolve-button-type.ce060f77.js";import"./RectangleStackIcon.e3df9db3.js";import"./FormInput.15b0f65d.js";import"./Modal.c8b43892.js";import"./disposables.a3cad5e2.js";import"./PlusCircleIcon.ee589c3e.js";import"./ArrowUturnLeftIcon.88830442.js";import"./CheckCircleIcon.a28321ba.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1155e6b8.js";const ee=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),te={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},oe={class:"flex justify-end"},le=e("span",null," Create ",-1),ne={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae={key:0},re=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ue={class:"flex space-x-1"},me=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),fe={class:"flex flex-col space-y-2"},pe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),xe={class:"font-medium"},he=e("span",null,"to",-1),ve={class:"font-medium"},_e=e("span",null,"of",-1),ye={class:"font-medium"},be=e("span",null,"results",-1),ke={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ce={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Be={class:"divide-x divide-gray-200"},Pe={class:"bg-white"},Le={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Oe=e("span",null," Delete ",-1),Ne={key:0},je=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[je],lt={__name:"Index",props:{users:Object,countries:Object,operators:Object,roles:Object,unbindedVends:Object},setup(l){const K=l,a=g({name:"",operator_id:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),B=g([]),x=g(!1),w=g(),C=g(""),V=g([]),h=q().props.auth.permissions;z(()=>{V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=V.value[0],B.value=[{id:"all",full_name:"All"},...K.operators.data.map(i=>({id:i.id,full_name:i.full_name}))]});function U(){C.value="create",w.value=null,x.value=!0}function E(i){!confirm("Are you sure to delete "+i.name+"?")||k.delete("/users/"+i.id)}function F(i){k.visit(route("users",{id:i.id}),{only:["unbindedVends"],preserveState:!0,replace:!0,onSuccess:n=>{}}),C.value="update",w.value=i,x.value=!0}function $(){k.get("/users",{...a.value,operator_id:a.value.operator_id.id,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){k.get("/users")}function P(i){a.value.sortKey=i,a.value.sortBy=!a.value.sortBy,$()}function A(){x.value=!1}return(i,n)=>(u(),y(I,null,[t(d(G),{title:"Users"}),t(D,null,{header:o(()=>[ee]),default:o(()=>{var L,S;return[e("div",te,[e("div",se,[e("div",oe,[d(h).includes("create users")?(u(),b(v,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=s=>U())},{default:o(()=>[t(d(J),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})):p("",!0)]),e("div",ne,[t(O,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":n[1]||(n[1]=s=>a.value.name=s)},{default:o(()=>[r(" Name ")]),_:1},8,["modelValue"]),t(O,{placeholderStr:"Email",modelValue:a.value.email,"onUpdate:modelValue":n[2]||(n[2]=s=>a.value.email=s)},{default:o(()=>[r(" Email ")]),_:1},8,["modelValue"]),d(h).includes("admin-access vends")?(u(),y("div",ae,[re,t(N,{modelValue:a.value.operator_id,"onUpdate:modelValue":n[3]||(n[3]=s=>a.value.operator_id=s),options:B.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0)]),e("div",ie,[e("div",de,[e("div",ue,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[4]||(n[4]=s=>$())},{default:o(()=>[t(d(Z),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[5]||(n[5]=s=>M())},{default:o(()=>[t(d(W),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",fe,[e("p",pe,[ge,e("span",xe,m((L=l.users.meta.from)!=null?L:0),1),he,e("span",ve,m((S=l.users.meta.to)!=null?S:0),1),_e,e("span",ye,m(l.users.meta.total),1),be]),t(N,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":n[6]||(n[6]=s=>a.value.numberPerPage=s),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:$},null,8,["modelValue","options"])])])]),e("div",ke,[e("div",we,[e("div",Ce,[e("table",Ve,[e("thead",$e,[e("tr",Be,[t(_,null,{default:o(()=>[r(" # ")]),_:1}),t(j,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:n[7]||(n[7]=s=>P("name"))},{default:o(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:o(()=>[r(" Email ")]),_:1}),t(_,null,{default:o(()=>[r(" Username ")]),_:1}),t(_,null,{default:o(()=>[r(" Role ")]),_:1}),t(j,{modelName:"operator_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:n[8]||(n[8]=s=>P("operator_id"))},{default:o(()=>[r(" Belongs to Operator ")]),_:1},8,["sortKey","sortBy"]),t(_)])]),e("tbody",Pe,[(u(!0),y(I,null,H(l.users.data,(s,c)=>(u(),y("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-center"},{default:o(()=>[r(m(l.users.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-left"},{default:o(()=>[r(m(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-left"},{default:o(()=>[r(m(s.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-center"},{default:o(()=>[r(m(s.username),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-center"},{default:o(()=>[r(m(s.roles[0]?s.roles[0].name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-center"},{default:o(()=>[r(m(s.operator.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:l.users.length,inputClass:"text-center"},{default:o(()=>[e("div",Le,[d(h).includes("update users")?(u(),b(v,{key:0,type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:T=>F(s)},{default:o(()=>[t(d(X),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])):p("",!0),d(h).includes("delete users")?(u(),b(v,{key:1,type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:T=>E(s)},{default:o(()=>[t(d(Y),{class:"w-4 h-4"}),Oe]),_:2},1032,["onClick"])):p("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.users.data.length?p("",!0):(u(),y("tr",Ne,Ie))])]),l.users.data.length?(u(),b(Q,{key:0,links:l.users.links,meta:l.users.meta},null,8,["links","meta"])):p("",!0)])])])]),x.value?(u(),b(R,{key:0,user:w.value,operators:l.operators,roles:l.roles,type:C.value,showModal:x.value,permissions:d(h),unbindedVends:l.unbindedVends,onModalClose:A},null,8,["user","operators","roles","type","showModal","permissions","unbindedVends"])):p("",!0)]}),_:1})],64))}};export{lt as default};
