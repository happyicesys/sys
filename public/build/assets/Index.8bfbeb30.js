import{_ as H}from"./Authenticated.8e5c093d.js";import{_ as h}from"./Button.08fa7a6e.js";import J from"./Form.1e0f46f5.js";import{_ as Q}from"./Paginator.8aeba371.js";import{_ as G,r as W}from"./SearchInput.c5c07396.js";import{_ as v}from"./MultiSelect.71a35338.js";import{_ as f,a as c}from"./TableData.e5500f4e.js";import{_ as O}from"./TableHeadSort.7787a90a.js";import{g as i,h as X,f as x,a as l,u as _,w as n,F as S,o as p,Z as Y,b as t,d as r,t as u,k as A,l as b,c as z,O as D}from"./app.bd296948.js";import{r as ee}from"./PlusIcon.a4b71971.js";import{r as te}from"./BackspaceIcon.1c5c601f.js";import{r as le}from"./PencilSquareIcon.3f23aae7.js";import"./keyboard.f0f5de9d.js";import"./use-resolve-button-type.6fe72ad8.js";import"./RectangleStackIcon.c071161d.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.ffc3634e.js";const ae=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Customers ",-1),oe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ne={class:"flex justify-end"},re=t("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ue=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ie=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category Group ",-1),me=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Status ",-1),ce=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Zone ",-1),ge=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Price Template ",-1),pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Tags ",-1),fe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ve={class:"mt-3"},xe={class:"flex space-x-1"},ye=t("span",null," Search ",-1),_e=t("span",null," Reset ",-1),he={class:"flex flex-col space-y-2"},be={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ke=t("span",null,"Showing",-1),Ve={class:"font-medium"},Ce=t("span",null,"to",-1),Be={class:"font-medium"},we=t("span",null,"of",-1),Le={class:"font-medium"},Pe=t("span",null,"results",-1),Oe={class:"mt-6 flex flex-col"},Se={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},$e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ie={class:"min-w-full border-separate",style:{"border-spacing":"0"}},je={class:"bg-gray-100"},Te={class:"divide-x divide-gray-200"},Ke={class:"bg-white"},Ne={key:0},Ue=t("br",null,null,-1),Ge={class:"inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"},Ae={class:"flex justify-center space-x-1"},ze=t("span",null," Edit ",-1),De={key:0},Fe=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Me=[Fe],rt={__name:"Index",props:{customers:Object,categories:Object,categoryGroups:Object,priceTemplates:Object,profiles:Object,statuses:Object,tags:Object,users:Object,zones:Object},setup(d){const g=d,a=i({name:"",status:"",sortKey:"",sortBy:!0,numberPerPage:100}),y=i(!1),k=i(),$=i([]),I=i([]),V=i([]),F=i([]),C=i([]),j=i([]),M=i([]),T=i([]),B=i(""),w=i([]);X(()=>{w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=w.value[0],$.value=g.categories.data.map(o=>({id:o.id,name:o.name})),I.value=g.categoryGroups.data.map(o=>({id:o.id,name:o.name})),V.value=g.priceTemplates.data.map(o=>({id:o.id,name:o.name})),F.value=g.profiles.data.map(o=>({id:o.id,name:o.name})),C.value=g.statuses.map(o=>({id:o.id,name:o.name})),M.value=g.users.data.map(o=>({id:o.id,name:o.name})),T.value=g.zones.data.map(o=>({id:o.id,name:o.name})),V.value=g.priceTemplates.data.map(o=>({id:o.id,name:o.name})),j.value=g.tags.data.map(o=>({id:o.id,name:o.name})),a.value.status=C.value[3]});function E(){B.value="create",k.value=null,y.value=!0}function Z(o){B.value="update",k.value=o,y.value=!0}function L(){D.get("/customers",{...a.value,status:a.value.status.id,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){D.get("/customers")}function P(o){a.value.sortKey=o,a.value.sortBy=!a.value.sortBy,L()}function q(){y.value=!1}return(o,s)=>(p(),x(S,null,[l(_(Y),{title:"Customers"}),l(H,null,{header:n(()=>[ae]),default:n(()=>{var K,N;return[t("div",oe,[t("div",se,[t("div",ne,[l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=e=>E())},{default:n(()=>[l(_(ee),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})]),t("div",de,[l(G,{placeholderStr:"ID",modelValue:a.value.code,"onUpdate:modelValue":s[1]||(s[1]=e=>a.value.code=e)},{default:n(()=>[r(" ID ")]),_:1},8,["modelValue"]),l(G,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[2]||(s[2]=e=>a.value.name=e)},{default:n(()=>[r(" ID Name ")]),_:1},8,["modelValue"]),t("div",null,[ue,l(v,{modelValue:a.value.categories,"onUpdate:modelValue":s[3]||(s[3]=e=>a.value.categories=e),options:$.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ie,l(v,{modelValue:a.value.categoryGroups,"onUpdate:modelValue":s[4]||(s[4]=e=>a.value.categoryGroups=e),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[me,l(v,{modelValue:a.value.status,"onUpdate:modelValue":s[5]||(s[5]=e=>a.value.status=e),options:C.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ce,l(v,{modelValue:a.value.zone_id,"onUpdate:modelValue":s[6]||(s[6]=e=>a.value.zone_id=e),options:T.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ge,l(v,{modelValue:a.value.price_template_id,"onUpdate:modelValue":s[7]||(s[7]=e=>a.value.price_template_id=e),options:V.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[pe,l(v,{modelValue:a.value.tags,"onUpdate:modelValue":s[8]||(s[8]=e=>a.value.tags=e),options:j.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",fe,[t("div",ve,[t("div",xe,[l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[9]||(s[9]=e=>L())},{default:n(()=>[l(_(W),{class:"h-4 w-4","aria-hidden":"true"}),ye]),_:1}),l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=e=>R())},{default:n(()=>[l(_(te),{class:"h-4 w-4","aria-hidden":"true"}),_e]),_:1})])]),t("div",he,[t("p",be,[ke,t("span",Ve,u((K=d.customers.meta.from)!=null?K:0),1),Ce,t("span",Be,u((N=d.customers.meta.to)!=null?N:0),1),we,t("span",Le,u(d.customers.meta.total),1),Pe]),l(v,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[11]||(s[11]=e=>a.value.numberPerPage=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:L},null,8,["modelValue","options"])])])]),t("div",Oe,[t("div",Se,[t("div",$e,[t("table",Ie,[t("thead",je,[t("tr",Te,[l(f,null,{default:n(()=>[r(" # ")]),_:1}),l(f,null,{default:n(()=>[r(" Vend ID ")]),_:1}),l(O,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[12]||(s[12]=e=>P("name"))},{default:n(()=>[r(" Customer ")]),_:1},8,["sortKey","sortBy"]),l(O,{modelName:"category_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[13]||(s[13]=e=>P("category_id"))},{default:n(()=>[r(" Category ")]),_:1},8,["sortKey","sortBy"]),l(O,{modelName:"category_group",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[14]||(s[14]=e=>P("category_group"))},{default:n(()=>[r(" Group ")]),_:1},8,["sortKey","sortBy"]),l(f,null,{default:n(()=>[r(" Del Address ")]),_:1}),l(f,null,{default:n(()=>[r(" Del Postcode ")]),_:1}),l(f,null,{default:n(()=>[r(" Tags ")]),_:1}),l(f,null,{default:n(()=>[r(" Zone ")]),_:1}),l(f,null,{default:n(()=>[r(" Status ")]),_:1}),l(f,null,{default:n(()=>[r(" Action ")]),_:1})])]),t("tbody",Ke,[(p(!0),x(S,null,A(d.customers.data,(e,m)=>(p(),x("tr",{key:e.id,class:"divide-x divide-gray-200"},[l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(d.customers.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.vendBindings&&e.vendBindings[0]?e.vendBindings[0].vend.code:""),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[e.virtual_customer_prefix&&e.virtual_customer_code?(p(),x("span",Ne,[r(u(e.virtual_customer_prefix)+"-"+u(e.virtual_customer_code)+" ",1),Ue])):b("",!0),r(" "+u(e.name),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.category?e.category.name:""),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.category&&e.category.categoryGroup?e.category.categoryGroup.name:""),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[r(u(e.deliveryAddress?e.deliveryAddress.full_address:null),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.deliveryAddress?e.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[(p(!0),x(S,null,A(e.tags,U=>(p(),x("span",Ge,u(U.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.zone?e.zone.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(u(e.status?e.status.name:""),1)]),_:2},1032,["currentIndex","totalLength"]),l(c,{currentIndex:m,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[t("div",Ae,[l(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:U=>Z(e)},{default:n(()=>[l(_(le),{class:"w-4 h-4"}),ze]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.customers.data.length?b("",!0):(p(),x("tr",De,Me))])]),d.customers.data.length?(p(),z(Q,{key:0,links:d.customers.links,meta:d.customers.meta},null,8,["links","meta"])):b("",!0)])])])]),y.value?(p(),z(J,{key:0,customer:k.value,type:B.value,showModal:y.value,onModalClose:q},null,8,["customer","type","showModal"])):b("",!0)]}),_:1})],64))}};export{rt as default};
