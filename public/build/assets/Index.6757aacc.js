import{_ as M}from"./Authenticated.28390677.js";import{_ as x}from"./Button.970da128.js";import T from"./Form.4e4cd88d.js";import{_ as A}from"./Paginator.ecee422f.js";import{_ as S,r as D}from"./SearchInput.e49b7c52.js";import{_ as R}from"./MultiSelect.3cb2fcdc.js";import{_ as h,a as f}from"./TableData.9fc636b2.js";import{_ as N}from"./TableHeadSort.e2824b9c.js";import{g as v,K as Z,h as q,f as b,a as t,u as i,w as s,F as K,o as d,Z as z,b as e,c as _,l as g,d as r,t as u,k as G,O as k}from"./app.ec18ef6a.js";import{r as H}from"./PlusIcon.3311e1f3.js";import{r as J}from"./BackspaceIcon.2c7b16fb.js";import{r as Q}from"./PencilSquareIcon.37ab6809.js";import{r as W}from"./TrashIcon.4d7d7f1a.js";import"./open-closed.21ff182f.js";import"./use-resolve-button-type.5c16f99c.js";import"./RectangleStackIcon.8d6c2b45.js";import"./FormInput.87616010.js";import"./Modal.7ae99a25.js";import"./platform.3f464bef.js";import"./PlusCircleIcon.a9428c09.js";import"./ArrowUturnLeftIcon.5408ee2d.js";import"./CheckCircleIcon.13add622.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.56960726.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),ne={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},le={class:"mt-3"},ae={class:"flex space-x-1"},re=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ce={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},pe=e("span",null,"of",-1),xe={class:"font-medium"},he=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},Ce={class:"bg-white"},$e={class:"flex justify-center space-x-1"},Ve=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Le={key:0},Pe=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Pe],et={__name:"Index",props:{users:Object,countries:Object,operators:Object,roles:Object,unbindedVends:Object},setup(o){const a=v({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),p=v(!1),w=v(),C=v(""),$=v([]),y=Z().props.auth.permissions;q(()=>{$.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=$.value[0]});function O(){C.value="create",w.value=null,p.value=!0}function j(m){!confirm("Are you sure to delete "+m.name+"?")||k.delete("/users/"+m.id)}function I(m){k.visit(route("users",{id:m.id}),{only:["unbindedVends"],preserveState:!0,replace:!0,onSuccess:l=>{}}),C.value="update",w.value=m,p.value=!0}function V(){k.get("/users",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function U(){k.get("/users")}function B(m){a.value.sortKey=m,a.value.sortBy=!a.value.sortBy,V()}function E(){p.value=!1}return(m,l)=>(d(),b(K,null,[t(i(z),{title:"Users"}),t(M,null,{header:s(()=>[X]),default:s(()=>{var L,P;return[e("div",Y,[e("div",ee,[e("div",te,[i(y).includes("create users")?(d(),_(x,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=n=>O())},{default:s(()=>[t(i(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})):g("",!0)]),e("div",ne,[t(S,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":l[1]||(l[1]=n=>a.value.name=n)},{default:s(()=>[r(" Name ")]),_:1},8,["modelValue"]),t(S,{placeholderStr:"Email",modelValue:a.value.email,"onUpdate:modelValue":l[2]||(l[2]=n=>a.value.email=n)},{default:s(()=>[r(" Email ")]),_:1},8,["modelValue"])]),e("div",oe,[e("div",le,[e("div",ae,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=n=>V())},{default:s(()=>[t(i(D),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[4]||(l[4]=n=>U())},{default:s(()=>[t(i(J),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[me,e("span",ce,u((L=o.users.meta.from)!=null?L:0),1),fe,e("span",ge,u((P=o.users.meta.to)!=null?P:0),1),pe,e("span",xe,u(o.users.meta.total),1),he]),t(R,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":l[5]||(l[5]=n=>a.value.numberPerPage=n),options:$.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:V},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",_e,[e("div",ye,[e("table",be,[e("thead",ke,[e("tr",we,[t(h,null,{default:s(()=>[r(" # ")]),_:1}),t(N,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:l[6]||(l[6]=n=>B("name"))},{default:s(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(h,null,{default:s(()=>[r(" Email ")]),_:1}),t(h,null,{default:s(()=>[r(" Username ")]),_:1}),t(h,null,{default:s(()=>[r(" Role ")]),_:1}),t(N,{modelName:"operator_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:l[7]||(l[7]=n=>B("operator_id"))},{default:s(()=>[r(" Belongs to Operator ")]),_:1},8,["sortKey","sortBy"]),t(h)])]),e("tbody",Ce,[(d(!0),b(K,null,G(o.users.data,(n,c)=>(d(),b("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-center"},{default:s(()=>[r(u(o.users.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-left"},{default:s(()=>[r(u(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-left"},{default:s(()=>[r(u(n.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-center"},{default:s(()=>[r(u(n.username),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-center"},{default:s(()=>[r(u(n.roles[0]?n.roles[0].name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-center"},{default:s(()=>[r(u(n.operator.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:c,totalLength:o.users.length,inputClass:"text-center"},{default:s(()=>[e("div",$e,[i(y).includes("update users")?(d(),_(x,{key:0,type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>I(n)},{default:s(()=>[t(i(Q),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])):g("",!0),i(y).includes("delete users")?(d(),_(x,{key:1,type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>j(n)},{default:s(()=>[t(i(W),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])):g("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.users.data.length?g("",!0):(d(),b("tr",Le,Se))])]),o.users.data.length?(d(),_(A,{key:0,links:o.users.links,meta:o.users.meta},null,8,["links","meta"])):g("",!0)])])])]),p.value?(d(),_(T,{key:0,user:w.value,operators:o.operators,roles:o.roles,type:C.value,showModal:p.value,permissions:i(y),unbindedVends:o.unbindedVends,onModalClose:E},null,8,["user","operators","roles","type","showModal","permissions","unbindedVends"])):g("",!0)]}),_:1})],64))}};export{et as default};